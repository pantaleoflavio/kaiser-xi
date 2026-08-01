<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Season $season;

    protected LeagueType $leagueType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->user = User::factory()->create();
        $this->season = Season::factory()->create([
            'real_competition_id' => RealCompetition::factory()->create()->id,
        ]);
        $this->leagueType = LeagueType::query()->where('key', 'classic')->firstOrFail();
    }

    public function test_authenticated_user_can_create_league_and_becomes_commissioner(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/leagues', [
            'name' => 'Weekend League',
            'season_id' => $this->season->id,
            'league_type_id' => $this->leagueType->id,
            'description' => 'Private league',
            'max_participants' => 12,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Weekend League')
            ->assertJsonPath('data.my_role', 'commissioner')
            ->assertJsonPath('data.season.id', $this->season->id)
            ->assertJsonPath('data.season.competition.id', $this->season->real_competition_id)
            ->assertJsonPath('data.type.key', 'classic')
            ->assertJsonPath('data.status.key', 'draft');

        $league = League::query()->where('name', 'Weekend League')->firstOrFail();
        $commissionerRole = LeagueRole::query()->where('key', 'commissioner')->firstOrFail();
        $draftStatus = LeagueStatus::query()->where('key', 'draft')->firstOrFail();

        $this->assertSame($draftStatus->id, $league->league_status_id);
        $this->assertSame($this->user->id, $league->commissioner_user_id);
        $this->assertSame(1, LeagueMembership::query()->where('league_id', $league->id)->where('user_id', $this->user->id)->count());
        $this->assertDatabaseHas('league_user', [
            'league_id' => $league->id,
            'user_id' => $this->user->id,
            'league_role_id' => $commissionerRole->id,
        ]);
        foreach (
            [
                LeagueSetting::BUDGET_RULES_MUTABLE,
                LeagueSetting::ROSTER_SIZE_MUTABLE,
                LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
            ] as $key
        ) {
            $this->assertDatabaseHas('league_settings', ['league_id' => $league->id, 'key' => $key]);
        }
    }

    public function test_unauthenticated_user_cannot_create_league(): void
    {
        $this->postJson('/api/v1/leagues', [
            'name' => 'Anonymous League',
            'season_id' => $this->season->id,
            'league_type_id' => $this->leagueType->id,
        ])->assertUnauthorized();
    }

    public function test_invalid_creation_references_are_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/leagues', [
            'name' => 'Invalid Season League',
            'season_id' => 999999,
            'league_type_id' => $this->leagueType->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('season_id');

        $this->postJson('/api/v1/leagues', [
            'name' => 'Invalid Type League',
            'season_id' => $this->season->id,
            'league_type_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('league_type_id');
    }

    public function test_client_cannot_assign_commissioner_or_membership_fields_on_create(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $participantRole = LeagueRole::query()->where('key', 'participant')->firstOrFail();

        $this->postJson('/api/v1/leagues', [
            'name' => 'Ownership Attempt League',
            'season_id' => $this->season->id,
            'league_type_id' => $this->leagueType->id,
            'commissioner_user_id' => $otherUser->id,
            'user_id' => $otherUser->id,
            'league_role_id' => $participantRole->id,
        ])->assertCreated();

        $league = League::query()->where('name', 'Ownership Attempt League')->firstOrFail();

        $this->assertSame($this->user->id, $league->commissioner_user_id);
        $this->assertDatabaseMissing('league_user', [
            'league_id' => $league->id,
            'user_id' => $otherUser->id,
        ]);
        $this->assertDatabaseHas('league_user', [
            'league_id' => $league->id,
            'user_id' => $this->user->id,
            'league_role_id' => LeagueRole::query()->where('key', 'commissioner')->value('id'),
        ]);
    }

    public function test_user_lists_only_leagues_where_they_are_member(): void
    {
        Sanctum::actingAs($this->user);

        $visibleLeague = League::factory()->create(['season_id' => $this->season->id]);
        $hiddenLeague = League::factory()->create(['season_id' => $this->season->id]);
        $this->attachMember($visibleLeague, $this->user, 'participant');

        $response = $this->getJson('/api/v1/leagues');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $visibleLeague->id)
            ->assertJsonPath('data.0.my_role', 'participant')
            ->assertJsonPath('data.0.season.id', $this->season->id)
            ->assertJsonPath('data.0.season.competition.id', $this->season->real_competition_id)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertNotContains($hiddenLeague->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_members_can_view_league_but_non_members_cannot(): void
    {
        $league = League::factory()->create(['season_id' => $this->season->id]);

        foreach (['commissioner', 'co_commissioner', 'participant'] as $role) {
            $member = User::factory()->create();
            $this->attachMember($league, $member, $role);
            Sanctum::actingAs($member);

            $this->getJson("/api/v1/leagues/{$league->id}")->assertOk();
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/leagues/{$league->id}")->assertForbidden();
    }

    public function test_only_commissioner_can_update_league(): void
    {
       $commissioner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::factory()->create([
            'season_id' => $this->season->id,
            'commissioner_user_id' => $commissioner->id,
        ]);
        $this->attachMember($league, $commissioner, 'commissioner');
        $this->attachMember($league, $participant, 'participant');

        Sanctum::actingAs($commissioner);
        $this->patchJson("/api/v1/leagues/{$league->id}", [
            'name' => 'Updated League Name',
            'commissioner_user_id' => $participant->id,
        ])->assertOk()->assertJsonPath('data.name', 'Updated League Name');

        $this->assertSame($commissioner->id, $league->fresh()->commissioner_user_id);

        Sanctum::actingAs($participant);
        $this->patchJson("/api/v1/leagues/{$league->id}", ['name' => 'Participant Update'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/v1/leagues/{$league->id}", ['name' => 'Outsider Update'])->assertForbidden();
    }

    public function test_only_commissioner_can_delete_league_and_memberships_are_removed(): void
    {
        $league = League::factory()->create(['season_id' => $this->season->id]);
        $commissioner = User::factory()->create();
        $participant = User::factory()->create();
        $this->attachMember($league, $commissioner, 'commissioner');
        $this->attachMember($league, $participant, 'participant');

        Sanctum::actingAs($participant);
        $this->deleteJson("/api/v1/leagues/{$league->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/leagues/{$league->id}")->assertForbidden();

        Sanctum::actingAs($commissioner);
        $this->deleteJson("/api/v1/leagues/{$league->id}")->assertNoContent();

        $this->assertDatabaseMissing('leagues', ['id' => $league->id]);
        $this->assertSame(0, LeagueMembership::query()->where('league_id', $league->id)->count());
    }

    public function test_members_can_list_members_without_sensitive_or_global_role_data(): void
    {
        $league = League::factory()->create(['season_id' => $this->season->id]);
        $commissioner = User::factory()->create();
        $coCommissioner = User::factory()->create();
        $participant = User::factory()->create();

        $this->attachMember($league, $commissioner, 'commissioner');
        $this->attachMember($league, $coCommissioner, 'co_commissioner');
        $this->attachMember($league, $participant, 'participant');

        foreach ([$commissioner, $coCommissioner, $participant] as $member) {
            Sanctum::actingAs($member);

            $response = $this->getJson("/api/v1/leagues/{$league->id}/members");

            $response
                ->assertOk()
                ->assertJsonFragment(['key' => 'commissioner'])
                ->assertJsonFragment(['key' => 'co_commissioner'])
                ->assertJsonFragment(['key' => 'participant'])
                ->assertJsonMissingPath('data.0.password')
                ->assertJsonMissingPath('data.0.email')
                ->assertJsonMissingPath('data.0.roles')
                ->assertJsonStructure(['data', 'links', 'meta']);
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/leagues/{$league->id}/members")->assertForbidden();
    }

    private function attachMember(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);
    }
}
