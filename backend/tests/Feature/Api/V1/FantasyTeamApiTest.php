<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FantasyTeamApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_commissioner_co_commissioner_and_participant_can_create_their_own_fantasy_team(): void
    {
        $createdTeamIds = [];

        foreach (['commissioner', 'co_commissioner', 'participant'] as $role) {
            [$league, $user] = $this->leagueWithMember($role);

            Sanctum::actingAs($user);

            $response = $this->postJson(
                "/api/v1/leagues/{$league->id}/fantasy-teams",
                [
                'name' => '  '.ucfirst($role).' Lions  ',
                ]
            );

            $response
                ->assertCreated()
                ->assertJsonPath('data.name', ucfirst($role) . ' Lions')
                ->assertJsonPath(
                    'data.slug',
                    strtolower(str_replace('_', '-', $role)) . '-lions'
                )
                ->assertJsonPath('data.owner.id', $user->id)
                ->assertJsonPath('data.league_id', $league->id)
                ->assertJsonPath('data.is_owned_by_current_user', true)
                ->assertJsonPath('data.budget', '500.00')
                ->assertJsonPath('data.remaining_budget', '500.00')
                ->assertJsonMissingPath('data.owner.email')
                ->assertJsonMissingPath('data.players');

            $teamId = $response->json('data.id');

            $createdTeamIds[] = $teamId;

            $this->assertDatabaseHas('fantasy_teams', [
                'id' => $teamId,
                'league_id' => $league->id,
                'user_id' => $user->id,
                'name' => ucfirst($role) . ' Lions',
                'budget' => 500,
                'remaining_budget' => 500,
                'logo_path' => null,
            ]);
        }

        $this->assertSame(
            0,
            FantasyTeamPlayer::query()
                ->whereIn('fantasy_team_id', $createdTeamIds)
                ->count()
        );
    }

    public function test_client_controlled_fields_are_rejected_when_creating(): void
    {
        [$league, $user] = $this->leagueWithMember();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", [
            'name' => 'Protected Fields FC',
            'league_id' => League::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'slug' => 'client-slug',
            'logo_path' => 'client.png',
            'budget' => 999,
            'remaining_budget' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['league_id', 'user_id', 'slug', 'logo_path', 'budget', 'remaining_budget']);
    }

    public function test_name_is_required_valid_string_and_limited_when_creating(): void
    {
        [$league, $user] = $this->leagueWithMember();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_duplicate_ownership_is_conflict(): void
    {
        [$league, $user] = $this->leagueWithMember();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'First'])->assertCreated();
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'Second'])->assertConflict();
    }

    public function test_same_user_can_create_fantasy_teams_in_different_leagues(): void
    {
        $user = User::factory()->create();
        $league = League::factory()->create();
        $otherLeague = League::factory()->create();
        $this->attachMember($league, $user, 'participant');
        $this->attachMember($otherLeague, $user, 'participant');

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'First'])->assertCreated();
        $this->postJson("/api/v1/leagues/{$otherLeague->id}/fantasy-teams", ['name' => 'Other'])->assertCreated();

        $this->assertSame(2, FantasyTeam::query()->where('user_id', $user->id)->count());
    }

    public function test_slug_collisions_are_resolved_with_incrementing_suffixes(): void
    {
        [$league, $first] = $this->leagueWithMember();
        $second = User::factory()->create();
        $this->attachMember($league, $second, 'participant');

        Sanctum::actingAs($first);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'Shared Name'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'shared-name');

        Sanctum::actingAs($second);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'Shared Name'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'shared-name-2');
    }

    public function test_non_member_and_global_admin_cannot_create_list_or_view_fantasy_teams(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $team = $this->teamForMember($league, $member);

        foreach ([User::factory()->create(), $this->globalAdmin()] as $user) {
            Sanctum::actingAs($user);

            $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'Nope'])->assertForbidden();
            $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams")->assertForbidden();
            $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}")->assertForbidden();
        }
    }

    public function test_member_can_list_fantasy_teams_in_their_league_only(): void
    {
        [$league, $viewer] = $this->leagueWithMember('co_commissioner');
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $otherLeague = League::factory()->create();
        $this->attachMember($league, $owner, 'participant');
        $this->attachMember($otherLeague, $otherOwner, 'participant');
        $team = $this->teamForMember($league, $owner, [
            'name' => 'A Team',
            'slug' => 'a-team',
            'budget' => 500,
            'remaining_budget' => 500,
        ]);
        $this->teamForMember($otherLeague, $otherOwner, [
            'name' => 'Z Team',
            'slug' => 'z-team',
            'budget' => 500,
            'remaining_budget' => 500,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $team->id)
            ->assertJsonPath('data.0.budget', '500.00')
            ->assertJsonPath('data.0.remaining_budget', '500.00')
            ->assertJsonMissingPath('data.0.owner.email')
            ->assertJsonMissingPath('data.0.players');
    }

    public function test_member_can_show_fantasy_team_in_their_league_with_safe_fields(): void
    {
        [$league, $viewer] = $this->leagueWithMember('co_commissioner');
        $owner = User::factory()->create();
        $this->attachMember($league, $owner, 'participant');
        $team = $this->teamForMember($league, $owner, [
            'name' => 'A Team',
            'slug' => 'a-team',
            'budget' => 500,
            'remaining_budget' => 500,
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.budget', '500.00')
            ->assertJsonPath('data.remaining_budget', '500.00')
            ->assertJsonMissingPath('data.owner.email')
            ->assertJsonMissingPath('data.players');
    }

    public function test_cross_league_nested_fantasy_team_access_is_not_found(): void
    {
        [$league, $user] = $this->leagueWithMember();
        $otherLeague = League::factory()->create();
        $this->attachMember($otherLeague, $user, 'participant');
        $team = $this->teamForMember($otherLeague, $user);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}")->assertNotFound();
        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => 'Bad'])->assertNotFound();
    }

    public function test_owner_can_rename_their_fantasy_team(): void
    {
        [$league, $owner] = $this->leagueWithMember();
        $team = $this->teamForMember($league, $owner, ['name' => 'Old Name', 'slug' => 'old-name']);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => ' New Name '])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'new-name');

        $this->assertDatabaseHas('fantasy_teams', [
            'id' => $team->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    public function test_other_members_league_managers_and_global_admin_cannot_rename_fantasy_team(): void
    {
        [$league, $owner] = $this->leagueWithMember('participant');
        $other = User::factory()->create();
        $commissioner = User::factory()->create();
        $coCommissioner = User::factory()->create();
        $this->attachMember($league, $other, 'participant');
        $this->attachMember($league, $commissioner, 'commissioner');
        $this->attachMember($league, $coCommissioner, 'co_commissioner');
        $team = $this->teamForMember($league, $owner);

        foreach ([$other, $commissioner, $coCommissioner, $this->globalAdmin()] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => 'Hacked'])->assertForbidden();
        }
    }

    public function test_update_rejects_client_controlled_fields(): void
    {
        [$league, $owner] = $this->leagueWithMember();
        $team = $this->teamForMember($league, $owner, ['budget' => 500, 'remaining_budget' => 500, 'logo_path' => null]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", [
            'name' => 'New Name',
            'league_id' => League::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'slug' => 'client-slug',
            'logo_path' => 'client.png',
            'budget' => 999,
            'remaining_budget' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['league_id', 'user_id', 'slug', 'logo_path', 'budget', 'remaining_budget']);

        $this->assertDatabaseHas('fantasy_teams', [
            'id' => $team->id,
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'budget' => '500.00',
            'remaining_budget' => '500.00',
            'logo_path' => null,
        ]);
    }

    public function test_rename_slug_collisions_use_the_existing_incrementing_suffix_rule(): void
    {
        [$league, $owner] = $this->leagueWithMember();
        $otherOwner = User::factory()->create();
        $this->attachMember($league, $otherOwner, 'participant');
        $team = $this->teamForMember($league, $owner, ['name' => 'Old Name', 'slug' => 'old-name']);
        $this->teamForMember($league, $otherOwner, ['name' => 'Shared Name', 'slug' => 'shared-name']);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", [
            'name' => 'Shared Name',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Shared Name')
            ->assertJsonPath('data.slug', 'shared-name-2');

        $this->assertDatabaseHas('fantasy_teams', [
            'id' => $team->id,
            'name' => 'Shared Name',
            'slug' => 'shared-name-2',
        ]);
    }

    public function test_name_is_required_valid_string_and_limited_when_updating(): void
    {
        [$league, $owner] = $this->leagueWithMember();
        $team = $this->teamForMember($league, $owner);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}", ['name' => str_repeat('a', 101)])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_accepting_invitation_does_not_create_fantasy_team_automatically(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $participant = User::factory()->create();

        $invitation = LeagueInvitation::factory()
            ->for($league)
            ->create([
                'created_by_user_id' => $commissioner->id,
                'invited_user_id' => $participant->id,
            ]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/v1/league-invitations/{$invitation->code}/accept")->assertCreated();

        $this->assertDatabaseMissing('fantasy_teams', [
            'league_id' => $league->id,
            'user_id' => $participant->id,
        ]);
    }

    public function test_member_can_create_fantasy_team_after_accepting_invitation(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $participant = User::factory()->create();

        $invitation = LeagueInvitation::factory()
            ->for($league)
            ->create([
                'created_by_user_id' => $commissioner->id,
                'invited_user_id' => $participant->id,
            ]);
        Sanctum::actingAs($participant);

        $this->postJson("/api/v1/league-invitations/{$invitation->code}/accept")->assertCreated();
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'New Member FC'])->assertCreated();

        $this->assertDatabaseHas('fantasy_teams', [
            'league_id' => $league->id,
            'user_id' => $participant->id,
        ]);
    }

    /**
     * @return array{League, User}
     */
    private function leagueWithMember(string $role = 'participant'): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $this->attachMember($league, $user, $role);

        return [$league, $user];
    }

    private function teamForMember(League $league, User $user, array $attributes = []): FantasyTeam
    {
        return FantasyTeam::factory()
            ->forLeagueAndUser($league, $user)
            ->create($attributes);
    }

    private function attachMember(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);
    }

    private function globalAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'global_admin')->firstOrFail()->id);

        return $user;
    }
}
