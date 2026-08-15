<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueScoringSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_scoring_defaults_are_initialized_exposed_and_idempotent(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $service = app(LeagueSettingsService::class);
        $service->initializeDefaults($league);
        $service->initializeDefaults($league);
        Sanctum::actingAs($commissioner);

        $this->getJson("/api/v1/leagues/{$league->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.real_captain_bonus_enabled', false)
            ->assertJsonPath('data.real_captain_bonus_points', 0.5)
            ->assertJsonPath('data.defense_modifier_enabled', false);

        foreach ([LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED, LeagueSetting::REAL_CAPTAIN_BONUS_POINTS, LeagueSetting::DEFENSE_MODIFIER_ENABLED] as $key) {
            $this->assertSame(1, $league->settings()->where('key', $key)->count());
        }
    }

    public function test_commissioner_and_co_commissioner_can_update_scoring_without_losing_other_settings(): void
    {
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $manager] = $this->leagueWithMember($role);
            app(LeagueSettingsService::class)->initializeDefaults($league);
            Sanctum::actingAs($manager);

            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'real_captain_bonus_enabled' => true,
                'real_captain_bonus_points' => 0.5,
                'defense_modifier_enabled' => true,
            ])->assertOk()
                ->assertJsonPath('data.real_captain_bonus_enabled', true)
                ->assertJsonPath('data.real_captain_bonus_points', 0.5)
                ->assertJsonPath('data.defense_modifier_enabled', true)
                ->assertJsonPath('data.initial_budget', LeagueSetting::DEFAULT_INITIAL_BUDGET);

            $this->assertSame(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS, $league->refresh()->rosterRoleLimits());
        }
    }

    public function test_participant_cannot_update_scoring_settings(): void
    {
        [$league, $participant] = $this->leagueWithMember('participant');
        Sanctum::actingAs($participant);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'real_captain_bonus_points' => 1.5,
        ])->assertForbidden();
    }

    public function test_real_captain_bonus_validation_accepts_decimals_and_rejects_invalid_values(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        foreach ([-0.01, 5.01, 'invalid', true] as $invalid) {
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'real_captain_bonus_points' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('real_captain_bonus_points');
        }

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['real_captain_bonus_points' => 0.5])
            ->assertOk()->assertJsonPath('data.real_captain_bonus_points', 0.5);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['real_captain_bonus_enabled' => 'enabled'])
            ->assertUnprocessable()->assertJsonValidationErrors('real_captain_bonus_enabled');

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'defense_modifier_enabled' => 'enabled',
        ])->assertUnprocessable()->assertJsonValidationErrors('defense_modifier_enabled');
    }

    public function test_completed_league_locks_scoring_settings(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $league->update(['league_status_id' => LeagueStatus::query()->where('key', LeagueStatus::COMPLETED)->value('id')]);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'real_captain_bonus_points' => 1.5,
        ])->assertConflict()->assertJsonPath('code', 'league_rules_locked');
    }

    private function leagueWithMember(string $role): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);

        return [$league, $user];
    }
}
