<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueSettingsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_refund_and_roster_increases_are_allowed(): void
    {
        [$league, $commissioner] = $this->activeLeague();

        Sanctum::actingAs($commissioner);

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['release_refund_percentage' => 75]
        )
            ->assertOk()
            ->assertJsonPath('data.release_refund_percentage', 75);

        $limits = [
            ...LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS,
            'forward' => 7,
        ];

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            [
                'max_roster_players' => 26,
                'roster_role_limits' => $limits,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.max_roster_players', 26)
            ->assertJsonPath('data.roster_role_limits.forward', 7);
    }

    public function test_initial_budget_is_not_changed_after_teams_exist(): void
    {
        [$league, $commissioner] = $this->activeLeague();

        Sanctum::actingAs($commissioner);

        $budgets = FantasyTeam::query()
            ->where('league_id', $league->id)
            ->pluck('remaining_budget', 'id');

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['initial_budget' => 999]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'initial_budget_change_unsupported');

        $this->assertSame(
            500,
            $league->refresh()->initialFantasyBudget()
        );

        $this->assertEquals(
            $budgets,
            FantasyTeam::query()
                ->where('league_id', $league->id)
                ->pluck('remaining_budget', 'id')
        );
    }

    public function test_unchanged_initial_budget_is_idempotent_after_teams_exist(): void
    {
        [$league, $commissioner] = $this->activeLeague('formula_one');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'initial_budget' => 500,
        ])->assertOk()->assertJsonPath('data.initial_budget', 500);
    }

    public function test_formula_one_position_points_remain_editable_after_initialization(): void
    {
        [$league, $commissioner] = $this->activeLeague('formula_one');
        $league->update(['championship_started_at' => now()]);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'formula_one_position_points' => LeagueSetting::DEFAULT_FORMULA_ONE_POSITION_POINTS,
        ])
            ->assertOk()
            ->assertJsonPath('data.locked_rule_groups', []);

        $changed = LeagueSetting::DEFAULT_FORMULA_ONE_POSITION_POINTS;
        $changed[1] = 26;

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'formula_one_position_points' => $changed,
        ])
            ->assertOk()
            ->assertJsonPath('data.formula_one_position_points.0', 26)
            ->assertJsonPath('data.locked_rule_groups', []);
    }

    public function test_head_to_head_goal_rules_remain_editable_after_schedule_initialization(): void
    {
        [$league, $commissioner] = $this->activeLeague('head_to_head');
        $league->update(['h2h_schedule_generated_at' => now()]);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'first_goal_threshold' => 68,
            'goal_interval' => 7,
        ])->assertOk()
            ->assertJsonPath('data.first_goal_threshold', 68)
            ->assertJsonPath('data.goal_interval', 7)
            ->assertJsonPath('data.locked_rule_groups', []);
    }

    public function test_common_scoring_rules_remain_editable_after_championship_initialization(): void
    {
        [$league, $commissioner] = $this->activeLeague();
        $league->update(['championship_started_at' => now()]);
        Sanctum::actingAs($commissioner);

        $thresholds = [
            ['id' => 'six', 'threshold' => 6, 'bonus' => 1.5],
            ['id' => 'seven', 'threshold' => 7, 'bonus' => 2.5],
        ];

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'real_captain_bonus_enabled' => true,
            'real_captain_bonus_points' => 1,
            'goalkeeper_clean_sheet_bonus_enabled' => true,
            'goalkeeper_clean_sheet_bonus_points' => 2,
            'defense_modifier_enabled' => true,
            'defense_modifier_thresholds' => $thresholds,
        ])->assertOk()
            ->assertJsonPath('data.real_captain_bonus_enabled', true)
            ->assertJsonPath('data.real_captain_bonus_points', 1)
            ->assertJsonPath('data.goalkeeper_clean_sheet_bonus_enabled', true)
            ->assertJsonPath('data.goalkeeper_clean_sheet_bonus_points', 2)
            ->assertJsonPath('data.defense_modifier_enabled', true)
            ->assertJsonPath('data.defense_modifier_thresholds', $thresholds);
    }

    public function test_roster_size_cannot_drop_below_largest_active_roster(): void
    {
        [$league, $commissioner] = $this->activeLeague();

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
        [$league, $commissioner] = $this->activeLeague();

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
                'released_on' => null,
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
        [$league, $commissioner] = $this->activeLeague();

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
        [$league, $commissioner] = $this->activeLeague();

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

    private function activeLeague(string $type = 'classic'): array
    {
        $commissioner = User::factory()->create();

        $league = League::factory()->create([
            'commissioner_user_id' => $commissioner->id,
            'league_type_id' => LeagueType::query()->where('key', $type)->value('id'),
        ]);

        $league->users()->attach($commissioner->id, [
            'league_role_id' => LeagueRole::query()
                ->where('key', 'commissioner')
                ->firstOrFail()
                ->id,
            'joined_at' => now(),
        ]);

        app(LeagueSettingsService::class)->initializeDefaults($league);

        FantasyTeam::factory()
            ->forLeagueAndUser($league, $commissioner)
            ->create([
                'budget' => $league->initialFantasyBudget(),
                'remaining_budget' => $league->initialFantasyBudget(),
            ]);

        SeasonClub::factory()->create([
            'season_id' => $league->season_id,
        ]);

        return [
            $league->refresh(),
            $commissioner,
        ];
    }
}
