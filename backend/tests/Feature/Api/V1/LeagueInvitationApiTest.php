<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LeagueInvitationStatus;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_commissioner_and_co_commissioner_can_create_and_revoke_but_participant_cannot(): void
    {
        $league = League::factory()->create(['max_participants' => 10]);
        $commissioner = $league->commissioner;
        $coCommissioner = User::factory()->create();
        $participant = User::factory()->create();
        $this->attach($league, $commissioner, 'commissioner');
        $this->attach($league, $coCommissioner, 'co_commissioner');
        $this->attach($league, $participant, 'participant');

        foreach ([$commissioner, $coCommissioner] as $manager) {
            $recipient = User::factory()->create();
            Sanctum::actingAs($manager);
            $created = $this->postJson("/api/v1/leagues/{$league->id}/invitations", [
                'email' => $recipient->email,
                'role' => 'participant',
                'expires_at' => now()->addDay()->toJSON(),
            ])->assertCreated()->assertJsonPath('data.status', 'pending');
            $id = $created->json('data.id');
            $this->deleteJson("/api/v1/leagues/{$league->id}/invitations/{$id}")->assertNoContent();
            $this->assertDatabaseHas('league_invitations', ['id' => $id, 'status' => 'revoked']);
        }
        Sanctum::actingAs($participant);
        $this->postJson("/api/v1/leagues/{$league->id}/invitations", ['email' => User::factory()->create()->email, 'role' => 'participant'])->assertForbidden();
    }

    public function test_creation_rejects_existing_member_duplicate_and_invalid_role(): void
    {
        $league = League::factory()->create(['max_participants' => 10]);
        $manager = $league->commissioner;
        $this->attach($league, $manager, 'commissioner');
        $member = User::factory()->create();
        $this->attach($league, $member, 'participant');
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/leagues/{$league->id}/invitations", ['email' => $member->email, 'role' => 'participant'])->assertConflict();
        $recipient = User::factory()->create();
        $payload = ['email' => $recipient->email, 'role' => 'co_commissioner'];
        $this->postJson("/api/v1/leagues/{$league->id}/invitations", $payload)->assertCreated();
        $this->postJson("/api/v1/leagues/{$league->id}/invitations", $payload)->assertConflict();
        $this->postJson("/api/v1/leagues/{$league->id}/invitations", ['email' => User::factory()->create()->email, 'role' => 'commissioner'])->assertUnprocessable();
    }

    public function test_recipient_inbox_is_private_and_only_contains_pending_unexpired_invitations(): void
    {
        $recipient = User::factory()->create();
        $own = $this->invitationFor($recipient);
        $this->invitationFor(User::factory()->create());
        $this->invitationFor($recipient, ['status' => LeagueInvitationStatus::Rejected]);
        $this->invitationFor($recipient, ['expires_at' => now()->subDay()]);
        Sanctum::actingAs($recipient);
        $this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)->assertJsonMissingPath('data.0.recipient.email');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/invitations')->assertUnauthorized();
    }

    public function test_recipient_can_accept_once_with_intended_role_and_another_user_cannot(): void
    {
        $recipient = User::factory()->create();
        $role = LeagueRole::query()->where('key', 'co_commissioner')->firstOrFail();
        $invitation = $this->invitationFor($recipient, ['league_role_id' => $role->id]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertNotFound();
        Sanctum::actingAs($recipient);
        $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertCreated()->assertJsonPath('data.role.key', 'co_commissioner');
        $this->assertDatabaseHas('league_user', ['league_id' => $invitation->league_id, 'user_id' => $recipient->id, 'league_role_id' => $role->id]);
        $this->assertSame(LeagueInvitationStatus::Accepted, $invitation->refresh()->status);
        $this->postJson("/api/v1/invitations/{$invitation->id}/accept")->assertConflict();
    }

    public function test_recipient_can_reject_without_membership_and_processed_or_expired_invitations_conflict(): void
    {
        $recipient = User::factory()->create();
        Sanctum::actingAs($recipient);
        $invitation = $this->invitationFor($recipient);
        $this->postJson("/api/v1/invitations/{$invitation->id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseMissing('league_user', ['league_id' => $invitation->league_id, 'user_id' => $recipient->id]);
        $this->postJson("/api/v1/invitations/{$invitation->id}/reject")->assertConflict();
        foreach ([['status' => LeagueInvitationStatus::Revoked], ['expires_at' => now()->subDay()]] as $state) {
            $other = $this->invitationFor($recipient, $state);
            $this->postJson("/api/v1/invitations/{$other->id}/reject")->assertConflict();
        }
    }

    private function invitationFor(User $recipient, array $state = []): LeagueInvitation
    {
        $league = League::factory()->create(['max_participants' => 10]);
        $this->attach($league, $league->commissioner, 'commissioner');
        return LeagueInvitation::factory()->for($league)->create([...$state, 'invited_user_id' => $recipient->id, 'created_by_user_id' => $league->commissioner_user_id]);
    }

    private function attach(League $league, User $user, string $role): void
    {
        if ($league->memberships()->where('user_id', $user->id)->exists()) return;
        $league->users()->attach($user->id, ['league_role_id' => LeagueRole::query()->where('key', $role)->value('id'), 'joined_at' => now()]);
    }
}
