<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Player;
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

    public function test_owner_can_manage_roster_and_member_can_view_it(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam(['remaining_budget' => 100, 'budget' => 100]);
        $viewer = User::factory()->create();
        $this->attachMember($league, $viewer, 'participant');
        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", [
            'player_id' => $player->id,
            'purchase_price' => 37,
        ])->assertCreated()->assertJsonPath('data.player.id', $player->id);
        $this->assertSame('63.00', $team->refresh()->remaining_budget);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_non_member_cannot_view_roster(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players"
        )->assertForbidden();
    }

    public function test_non_owner_cannot_add_player_to_roster(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();

        $member = User::factory()->create();
        $this->attachMember($league, $member, 'participant');

        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players",
            [
                'player_id' => $player->id,
                'purchase_price' => 1,
            ]
        )->assertForbidden();
    }

    public function test_non_owner_cannot_release_player_from_roster(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();

        $member = User::factory()->create();
        $this->attachMember($league, $member, 'participant');

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

        Sanctum::actingAs($member);

        $this->deleteJson(
            "/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$player->id}"
        )->assertForbidden();
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
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 5, 'league_id' => 999, 'fantasy_team_id' => 999, 'assigned_by_user_id' => 999, 'remaining_budget' => 999])->assertUnprocessable()->assertJsonValidationErrors(['league_id', 'fantasy_team_id', 'assigned_by_user_id', 'remaining_budget']);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 5])->assertCreated();
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    public function test_same_player_can_be_assigned_in_different_leagues_but_once_per_league(): void
    {
        [$league, $owner, $team] = $this->leagueOwnerAndTeam();
        [$sameLeague, $otherOwner, $otherTeam] = [$league, User::factory()->create(), null];
        $this->attachMember($sameLeague, $otherOwner, 'participant');
        $otherTeam = FantasyTeam::factory()->forLeagueAndUser($sameLeague, $otherOwner)->create(['budget' => 500, 'remaining_budget' => 500]);
        $player = $this->eligiblePlayer($league);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])->assertCreated();
        Sanctum::actingAs($otherOwner);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$otherTeam->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])->assertUnprocessable();

        [$otherLeague, $owner2, $team2] = $this->leagueOwnerAndTeam();
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $otherLeague->season_id])->id]);
        Sanctum::actingAs($owner2);
        $this->postJson("/api/v1/leagues/{$otherLeague->id}/fantasy-teams/{$team2->id}/players", ['player_id' => $player->id, 'purchase_price' => 1])->assertCreated();
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
        [$league, $owner, $team] = $this->leagueOwnerAndTeam(['budget' => 100, 'remaining_budget' => 100]);
        $player = $this->eligiblePlayer($league);
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 50]);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $player->id, 'purchase_price' => 37])->assertCreated();
        $this->deleteJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$player->id}")->assertOk();
        $this->assertSame('82.00', $team->refresh()->remaining_budget);
        $this->assertSame(1, FantasyTeamPlayer::query()->whereNotNull('released_at')->count());

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 0]);
        $second = $this->eligiblePlayer($league);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $second->id, 'purchase_price' => 10])->assertCreated();
        $this->deleteJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$second->id}")->assertOk();
        $this->assertSame('72.00', $team->refresh()->remaining_budget);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 100]);
        $third = $this->eligiblePlayer($league);
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players", ['player_id' => $third->id, 'purchase_price' => 10])->assertCreated();
        $this->deleteJson("/api/v1/leagues/{$league->id}/fantasy-teams/{$team->id}/players/{$third->id}")->assertOk();
        $this->assertSame('72.00', $team->refresh()->remaining_budget);
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

    private function eligiblePlayer(League $league): Player
    {
        $player = Player::factory()->create();
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id]);
        return $player;
    }
}
