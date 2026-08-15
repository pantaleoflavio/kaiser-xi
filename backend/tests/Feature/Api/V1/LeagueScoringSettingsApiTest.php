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
            ->assertJsonPath('data.captain_score_multiplier', 2)
            ->assertJsonPath('data.defense_modifier_enabled', false);

        foreach ([LeagueSetting::CAPTAIN_SCORE_MULTIPLIER, LeagueSetting::DEFENSE_MODIFIER_ENABLED] as $key) {
            $this->assertSame(1, $league->settings()->where('key', $key)->count());
        }
    }

    public function test_commissioner_can_update_scoring_without_losing_other_settings(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        app(LeagueSettingsService::class)->initializeDefaults($league);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'captain_score_multiplier' => 1.5,
            'defense_modifier_enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.captain_score_multiplier', 1.5)
            ->assertJsonPath('data.defense_modifier_enabled', true)
            ->assertJsonPath('data.initial_budget', LeagueSetting::DEFAULT_INITIAL_BUDGET);

        $this->assertSame(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS, $league->refresh()->rosterRoleLimits());
    }

    public function test_participant_cannot_update_scoring_settings(): void
    {
        [$league, $participant] = $this->leagueWithMember('participant');
        Sanctum::actingAs($participant);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'captain_score_multiplier' => 1.5,
        ])->assertForbidden();
    }

    public function test_captain_multiplier_validation_rejects_invalid_values(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        foreach ([-1, 0, 0.99, 3.01, 4, 'double', true] as $invalid) {
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'captain_score_multiplier' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('captain_score_multiplier');
        }

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
            'captain_score_multiplier' => 1.5,
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
