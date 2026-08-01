<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
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

    public function test_locked_active_rule_groups_cannot_change(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();

        Sanctum::actingAs($commissioner);

        $currentLimits = $league->rosterRoleLimits();

        $validChangedLimits = [
            ...$currentLimits,
            'forward' => $currentLimits['forward'] + 1,
        ];

        $changes = [
            ['initial_budget' => $league->initialFantasyBudget() + 100],
            ['release_refund_percentage' => 60],
            ['max_roster_players' => $league->maxRosterPlayers() - 1],
            ['roster_role_limits' => $validChangedLimits],
        ];

        foreach ($changes as $change) {
            $this->patchJson(
                "/api/v1/leagues/{$league->id}/settings",
                $change
            )
                ->assertConflict()
                ->assertJsonPath('code', 'league_rules_locked');
        }
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

    public function test_mutable_roster_size_cannot_drop_below_largest_active_roster(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague(['roster_size_mutable' => true]);
        Sanctum::actingAs($commissioner);
        $largest = FantasyTeamPlayer::query()->active()->where('league_id', $league->id)
            ->selectRaw('fantasy_team_id, count(*) aggregate')->groupBy('fantasy_team_id')->pluck('aggregate')->max();

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['max_roster_players' => $largest - 1])
            ->assertConflict()->assertJsonPath('code', 'roster_size_incompatible');
    }

   public function test_mutable_role_limit_cannot_drop_below_existing_composition(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague([
            'roster_role_limits_mutable' => true,
        ]);

        Sanctum::actingAs($commissioner);

        $assignment = FantasyTeamPlayer::query()
            ->active()
            ->where('league_id', $league->id)
            ->with('player')
            ->firstOrFail();

        $registration = $assignment->player
            ->playerSeasonRegistrations()
            ->with('playerRole')
            ->whereHas(
                'seasonClub',
                fn ($query) => $query->where(
                    'season_clubs.season_id',
                    $league->season_id
                )
            )
            ->firstOrFail();

        $role = $registration->playerRole->key;

        $limits = $league->rosterRoleLimits();
        $removedLimit = $limits[$role];

        $limits[$role] = 0;

        $compensatingRole = $role === 'forward'
            ? 'midfielder'
            : 'forward';

        $limits[$compensatingRole] += $removedLimit;

        $this->patchJson(
            "/api/v1/leagues/{$league->id}/settings",
            ['roster_role_limits' => $limits]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'roster_role_limit_incompatible');
    }

    public function test_mutability_flags_cannot_change_after_activation(): void
    {
        [$league, $commissioner] = $this->activeDemoLeague();
        Sanctum::actingAs($commissioner);
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['budget_rules_mutable' => true])
            ->assertConflict()->assertJsonPath('code', 'league_mutability_flags_locked');
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