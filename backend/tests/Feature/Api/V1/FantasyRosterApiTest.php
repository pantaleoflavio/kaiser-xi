<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FantasyRosterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_commissioner_can_assign_to_own_team_and_member_can_view_it(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam(['remaining_budget' => 100, 'budget' => 100]);
        $viewer = User::factory()->create();
        $this->attachMember($league, $viewer, 'participant');
        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($owner);
        $assignmentResponse = $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
            'player_id' => $player->id,
            'purchase_price' => 37,
        ])->assertCreated()->assertJsonPath('data.player.id', $player->id);
        $this->assertIsInt($assignmentResponse->json('data.id'));
        $this->assertIsInt($assignmentResponse->json('data.id'));
        $this->assertSame('63.00', $team->refresh()->remaining_budget);
        $this->assertDatabaseHas('fantasy_team_players', [
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_commissioner_can_assign_to_another_participants_team_and_target_budget_is_deducted(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $participant = User::factory()->create();
        $this->attachMember($league, $participant, 'participant');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $participant)->create([
            'budget' => 100,
            'remaining_budget' => 100,
        ]);
        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
            'player_id' => $player->id,
            'purchase_price' => 37,
        ])->assertCreated();

        $this->assertSame($participant->id, $team->refresh()->user_id);
        $this->assertSame('63.00', $team->remaining_budget);
        $this->assertDatabaseHas('fantasy_team_players', [
            'fantasy_team_id' => $team->id,
            'assigned_by_user_id' => $commissioner->id,
        ]);
    }

    public function test_co_commissioner_can_assign_to_another_participants_team(): void
    {
        [$league, $coCommissioner] = $this->leagueWithMember('co_commissioner');
        $participant = User::factory()->create();
        $this->attachMember($league, $participant, 'participant');
        $team = FantasyTeam::factory()
            ->forLeagueAndUser($league, $participant)
            ->create([
                'budget' => 500,
                'remaining_budget' => 500,
            ]);

        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($coCommissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
            'player_id' => $player->id,
            'purchase_price' => 10,
        ])->assertCreated();

        $this->assertDatabaseHas('fantasy_team_players', [
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $coCommissioner->id,
            'purchase_price' => 10,
            'released_at' => null,
        ]);

        $this->assertSame('490.00', $team->refresh()->remaining_budget);
    }

    public function test_non_member_cannot_view_roster(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players"
        )->assertForbidden();
    }

    public function test_ordinary_owner_cannot_add_player_to_own_roster(): void
    {
        [$league, $owner] = $this->leagueWithMember('participant');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $owner)->create();

        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ]
        )->assertForbidden();
    }

    public function test_ordinary_owner_cannot_release_player_from_own_roster(): void
    {
        [$league, $owner] = $this->leagueWithMember('participant');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $owner)->create();
        $player = $this->eligiblePlayer($league);

        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $owner->id,
            'purchase_price' => 1,
            'assigned_at' => now(),
            'released_at' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$player->id}"
        )->assertForbidden();
    }

    public function test_commissioner_can_release_from_another_participants_team_and_target_receives_refund(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $participant = User::factory()->create();
        $this->attachMember($league, $participant, 'participant');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $participant)->create([
            'budget' => 100,
            'remaining_budget' => 63,
        ]);
        $player = $this->eligiblePlayer($league);
        FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $commissioner->id,
            'purchase_price' => 37,
        ]);

        Sanctum::actingAs($commissioner);
        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$player->id}"
        )->assertOk();

        $this->assertSame($participant->id, $team->refresh()->user_id);
        $this->assertSame('82.00', $team->remaining_budget);
        $this->assertDatabaseHas('fantasy_team_players', [
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'released_by_user_id' => $commissioner->id,
        ]);
    }

    public function test_ordinary_participant_non_member_and_global_admin_cannot_manage_another_team(): void
    {
        [$league, $commissioner, $team] = $this->leagueOwnerAndTeam();
        $player = $this->eligiblePlayer($league);
        $participant = User::factory()->create();
        $this->attachMember($league, $participant, 'participant');
        $nonMember = User::factory()->create();
        $globalAdmin = User::factory()->globalAdmin()->create();

        foreach ([$participant, $nonMember, $globalAdmin] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('fantasy_team_players', ['player_id' => $player->id]);
    }

    public function test_cross_league_roster_access_is_not_found(): void
    {
        [$league, $user] = $this->leagueWithMember('participant');
        [$otherLeague, $owner, $team] = $this->leagueOwnerAndTeam();
        $this->attachMember($otherLeague, $user, 'participant');
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players")->assertNotFound();
    }

    public function test_player_assignment_rules_and_client_fields_are_enforced(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam(['remaining_budget' => 10, 'budget' => 10]);
        $player = $this->eligiblePlayer($league);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 11])->assertUnprocessable()->assertJsonValidationErrors('purchase_price');
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 5, 'league_id' => 999, 'fantasy_team_id' => 999, 'assigned_by_user_id' => 999, 'released_by_user_id' => 999, 'remaining_budget' => 999])->assertUnprocessable()->assertJsonValidationErrors(['league_id', 'fantasy_team_id', 'assigned_by_user_id', 'released_by_user_id', 'remaining_budget']);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 5])->assertCreated();
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    public function test_same_player_can_be_assigned_in_different_leagues_but_once_per_league(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');

        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        $this->attachMember($league, $firstOwner, 'participant');
        $this->attachMember($league, $secondOwner, 'participant');

        $firstTeam = FantasyTeam::factory()
            ->forLeagueAndUser($league, $firstOwner)
            ->create([
                'budget' => 500,
                'remaining_budget' => 500,
            ]);

        $secondTeam = FantasyTeam::factory()
            ->forLeagueAndUser($league, $secondOwner)
            ->create([
                'budget' => 500,
                'remaining_budget' => 500,
            ]);

        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($commissioner);

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$firstTeam->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$secondTeam->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ]
        )->assertUnprocessable();

        [$otherLeague, $otherCommissioner] = $this->leagueWithMember('commissioner');

        $otherParticipant = User::factory()->create();
        $this->attachMember($otherLeague, $otherParticipant, 'participant');

        $otherTeam = FantasyTeam::factory()
            ->forLeagueAndUser($otherLeague, $otherParticipant)
            ->create([
                'budget' => 500,
                'remaining_budget' => 500,
            ]);

        PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create([
                'season_id' => $otherLeague->season_id,
            ])->id,
        ]);

        Sanctum::actingAs($otherCommissioner);

        $this->postJson(
            "/api/v1/leagues/{$otherLeague->id}/fantasy-teams/{$otherTeam->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ]
        )->assertCreated();
    }

    public function test_invalid_season_player_is_rejected(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();
        $player = Player::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    public function test_release_refund_uses_configured_half_up_percentage_and_preserves_history(): void
    {
        [$league, $commissioner, $team] = $this->leagueOwnerAndTeam([
            'budget' => 100,
            'remaining_budget' => 100,
        ]);

        Sanctum::actingAs($commissioner);

        $player = $this->eligiblePlayer($league);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 50]
        )->assertOk();

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 37,
            ]
        )->assertCreated();

        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$player->id}"
        )->assertOk();

        $this->assertSame('82.00', $team->refresh()->remaining_budget);

        $firstReleasedAssignment = FantasyTeamPlayer::query()
            ->where('league_id', $league->id)
            ->where('fantasy_team_id', $team->id)
            ->where('player_id', $player->id)
            ->sole();

        $this->assertNotNull($firstReleasedAssignment->released_at);
        $this->assertSame('37.00', $firstReleasedAssignment->purchase_price);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 0]
        )->assertOk();

        $secondPlayer = $this->eligiblePlayer($league);

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players",
            [
                'player_id' => $secondPlayer->id,
                'purchase_price' => 10,
            ]
        )->assertCreated();

        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$secondPlayer->id}"
        )->assertOk();

        $this->assertSame('72.00', $team->refresh()->remaining_budget);

        $secondReleasedAssignment = FantasyTeamPlayer::query()
            ->where('league_id', $league->id)
            ->where('fantasy_team_id', $team->id)
            ->where('player_id', $secondPlayer->id)
            ->sole();

        $this->assertNotNull($secondReleasedAssignment->released_at);
        $this->assertSame('10.00', $secondReleasedAssignment->purchase_price);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 100]
        )->assertOk();

        $thirdPlayer = $this->eligiblePlayer($league);

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players",
            [
                'player_id' => $thirdPlayer->id,
                'purchase_price' => 10,
            ]
        )->assertCreated();

        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$thirdPlayer->id}"
        )->assertOk();

        $this->assertSame('72.00', $team->refresh()->remaining_budget);

        $thirdReleasedAssignment = FantasyTeamPlayer::query()
            ->where('league_id', $league->id)
            ->where('fantasy_team_id', $team->id)
            ->where('player_id', $thirdPlayer->id)
            ->sole();

        $this->assertNotNull($thirdReleasedAssignment->released_at);
        $this->assertSame('10.00', $thirdReleasedAssignment->purchase_price);

        $this->assertSame(
            3,
            FantasyTeamPlayer::query()
                ->where('league_id', $league->id)
                ->where('fantasy_team_id', $team->id)
                ->whereNotNull('released_at')
                ->count()
        );
    }

    public function test_assignment_fails_when_active_total_roster_cap_is_reached(): void
    {
        [$league, $commissioner, $team] = $this->leagueOwnerAndTeam();
        $this->configureRosterRules($league, 1, ['goalkeeper' => 1, 'defender' => 1, 'midfielder' => 1, 'forward' => 1]);
        Sanctum::actingAs($commissioner);

        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'goalkeeper'))->assertCreated();
        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'defender'))
            ->assertConflict()
            ->assertJsonPath('code', 'roster_full')
            ->assertJsonPath('message', 'The fantasy team roster has reached its maximum active player count.');
    }

    public function test_assignment_enforces_registration_role_quota_but_allows_another_role(): void
    {
        [$league, $commissioner, $team] = $this->leagueOwnerAndTeam();
        $this->configureRosterRules($league, 3, ['goalkeeper' => 1, 'defender' => 2, 'midfielder' => 0, 'forward' => 0]);
        Sanctum::actingAs($commissioner);

        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'goalkeeper'))->assertCreated();
        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'goalkeeper'))
            ->assertConflict()
            ->assertJsonPath('code', 'roster_role_limit_reached')
            ->assertJsonPath('message', 'The fantasy team roster has reached the active player limit for role [goalkeeper].');
        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'defender'))->assertCreated();
    }

    public function test_released_assignments_do_not_count_and_history_is_preserved(): void
    {
        [$league, $commissioner, $team] = $this->leagueOwnerAndTeam();
        $this->configureRosterRules($league, 1, ['goalkeeper' => 1, 'defender' => 0, 'midfielder' => 0, 'forward' => 0]);
        Sanctum::actingAs($commissioner);
        $first = $this->eligiblePlayer($league, 'goalkeeper');

        $this->assignPlayer($league, $team, $first)->assertCreated();
        $this->deleteJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$first->id}")->assertOk();
        $this->assignPlayer($league, $team, $this->eligiblePlayer($league, 'goalkeeper'))->assertCreated();

        $this->assertSame(2, FantasyTeamPlayer::query()->where('fantasy_team_id', $team->id)->count());
        $this->assertSame(1, FantasyTeamPlayer::query()->where('fantasy_team_id', $team->id)->whereNotNull('released_at')->count());
    }

    private function leagueOwnerAndTeam(array $teamAttributes = []): array
    {
        [$league, $owner] = $this->leagueWithMember('commissioner');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $owner)->create($teamAttributes + ['budget' => 500, 'remaining_budget' => 500]);

        return [$league, $owner, $team];
    }

    private function leagueWithMember(string $role): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $this->attachMember($league, $user, $role);

        return [$league, $user];
    }

    private function attachMember(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, ['league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id, 'joined_at' => now()]);
    }

    private function eligiblePlayer(League $league, ?string $roleKey = null): Player
    {
        $player = Player::factory()->create();
        PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id,
            ...($roleKey ? ['player_role_id' => PlayerRole::query()->where('key', $roleKey)->firstOrFail()->id] : []),
        ]);

        return $player;
    }

    private function configureRosterRules(League $league, int $maximum, array $limits): void
    {
        $league->settings()->updateOrCreate(
            ['key' => LeagueSetting::MAX_ROSTER_PLAYERS],
            ['value' => LeagueSetting::integerPayload(LeagueSetting::MAX_ROSTER_PLAYERS, $maximum)],
        );
        $league->settings()->updateOrCreate(
            ['key' => LeagueSetting::ROSTER_ROLE_LIMITS],
            ['value' => LeagueSetting::roleLimitsPayload($limits)],
        );
    }

    private function assignPlayer(League $league, FantasyTeam $team, Player $player)
    {
        return $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
            'player_id' => $player->id,
            'purchase_price' => 1,
        ]);
    }
}
