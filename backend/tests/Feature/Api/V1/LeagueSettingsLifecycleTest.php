<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueSettingsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_mutable_refund_and_roster_increases_are_allowed(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague([
            'budget_rules_mutable' => true,
            'roster_size_mutable' => true,
            'roster_role_limits_mutable' => true,
        ]);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 75])
            ->assertOk()->assertJsonPath('data.release_refund_percentage', 75);
        $limits = [...LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS, 'forward' => 7];
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'max_roster_players' => 26,
            'roster_role_limits' => $limits,
        ])->assertOk()
            ->assertJsonPath('data.max_roster_players', 26)
            ->assertJsonPath('data.roster_role_limits.forward', 7);
    }

    public function test_initial_budget_is_not_changed_after_teams_exist(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague(['budget_rules_mutable' => true]);
        Sanctum::actingAs($commissioner);
        $budgets = FantasyTeam::query()->where('league_id', $league->id)->pluck('remaining_budget', 'id');

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['initial_budget' => 999])
            ->assertConflict()->assertJsonPath('code', 'initial_budget_change_unsupported');

        $this->assertSame(500, $league->refresh()->initialFantasyBudget());
        $this->assertEquals($budgets, FantasyTeam::query()->where('league_id', $league->id)->pluck('remaining_budget', 'id'));
    }

    public function test_roster_size_cannot_drop_below_largest_active_roster(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();

        Sanctum::actingAs($commissioner);

        $team = FantasyTeam::query()
            ->where('league_id', $league->id)
            ->firstOrFail();

        while ($team->activePlayerAssignments()->count() < 12) {
            FantasyTeamPlayer::factory()->create([
                'league_id' => $league->id,
                'fantasy_team_id' => $team->id,
                'released_at' => null,
            ]);
        }

        $this->assertSame(
            12,
            $team->activePlayerAssignments()->count()
        );

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['max_roster_players' => 11]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'roster_size_incompatible');
    }

    public function test_role_limit_cannot_drop_below_existing_composition(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();

        Sanctum::actingAs($commissioner);

        $team = FantasyTeam::query()
            ->where('league_id', $league->id)
            ->firstOrFail();

        $defenderRoleId = PlayerRole::query()
            ->where('key', 'defender')
            ->value('id');

        $seasonClub = SeasonClub::query()
            ->where('season_id', $league->season_id)
            ->firstOrFail();

        for ($i = 0; $i < 6; $i++) {
            $player = Player::factory()->create();

            PlayerSeasonRegistration::factory()->create([
                'player_id' => $player->id,
                'season_club_id' => $seasonClub->id,
                'player_role_id' => $defenderRoleId,
                'is_active' => true,
                'released_at' => null,
            ]);

            FantasyTeamPlayer::factory()->create([
                'league_id' => $league->id,
                'fantasy_team_id' => $team->id,
                'player_id' => $player->id,
                'released_at' => null,
            ]);
        }

        $limits = $league->rosterRoleLimits();

        $removed = $limits['defender'] - 5;

        $limits['defender'] = 5;
        $limits['midfielder'] += $removed;

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            [
                'roster_role_limits' => $limits,
            ]
        )
            ->assertConflict()
            ->assertJsonPath(
                'code',
                'roster_role_limit_incompatible'
            );
    }

    public function test_completed_league_cannot_update_settings(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();

        $league->update([
            'league_status_id' => LeagueStatus::query()
                ->where('key', LeagueStatus::COMPLETED)
                ->value('id'),
        ]);

        Sanctum::actingAs($commissioner);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 20]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'league_rules_locked');
    }

    public function test_archived_league_cannot_update_settings(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();

        $league->update([
            'league_status_id' => LeagueStatus::query()
                ->where('key', LeagueStatus::ARCHIVED)
                ->value('id'),
        ]);

        Sanctum::actingAs($commissioner);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 20]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'league_rules_locked');
    }

    private function activeDemoLeague(array $mutability = []): array
    {
        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $commissioner = User::query()->where('email', 'demo.commissioner@example.com')->firstOrFail();
        app(LeagueSettingsService::class)->update($league, $mutability);
        $league->update(['league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->value('id')]);

        return [$league->refresh(), $commissioner];
    }
}