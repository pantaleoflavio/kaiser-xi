<?php

namespace Tests\Feature\Api\V1;

use App\Models\FormationModule;
use App\Models\FormationModuleRequirement;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueLineupSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_defaults_are_persisted_once_and_returned_with_deterministic_formations(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        $service = app(LeagueSettingsService::class);
        $service->initializeDefaults($league);
        $service->initializeDefaults($league);
        Sanctum::actingAs($commissioner);

        $response = $this->getJson("/api/v1/leagues/{$league->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.allowed_formation_module_names', LeagueSetting::DEFAULT_ALLOWED_FORMATION_MODULE_NAMES)
            ->assertJsonPath('data.bench_size', 7)
            ->assertJsonPath('data.bench_role_limits', LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS)
            ->assertJsonPath('data.max_substitutions', 3)
            ->assertJsonPath('data.substitution_order_mode', 'bench_order')
            ->assertJsonPath('data.allow_formation_change_on_substitution', false)
            ->assertJsonPath('data.captain_enabled', false);

        $this->assertSame(LeagueSetting::DEFAULT_ALLOWED_FORMATION_MODULE_NAMES, array_column($response->json('data.allowed_formation_modules'), 'name'));
        $this->assertIsInt($response->json('data.allowed_formation_modules.0.id'));
        $this->assertSame([1, 3, 4, 3], array_values($response->json('data.allowed_formation_modules.0.requirements')));
        foreach ($this->lineupSettingKeys() as $key) {
            $this->assertSame(1, $league->settings()->where('key', $key)->count());
        }
    }

    public function test_commissioner_and_co_commissioner_can_update_allowed_formations_with_duplicates_normalized(): void
    {
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $member] = $this->leagueWithMember($role);
            Sanctum::actingAs($member);

            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'allowed_formation_module_names' => ['4-4-2', '3-4-3', '4-4-2'],
            ])->assertOk()->assertJsonPath('data.allowed_formation_module_names', ['3-4-3', '4-4-2']);
        }
    }

    public function test_invalid_or_incomplete_allowed_formations_are_rejected(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['allowed_formation_module_names' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('allowed_formation_module_names');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['allowed_formation_module_names' => ['unknown']])
            ->assertUnprocessable()->assertJsonValidationErrors('allowed_formation_module_names');

        $module = FormationModule::query()->where('name', '4-3-3')->firstOrFail();
        FormationModuleRequirement::query()->where('formation_module_id', $module->id)->delete();
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['allowed_formation_module_names' => ['4-3-3']])
            ->assertUnprocessable()->assertJsonValidationErrors('allowed_formation_module_names');
    }

    public function test_bench_rules_validate_shape_ranges_sum_and_persisted_companions(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'bench_size' => 5,
            'bench_role_limits' => ['goalkeeper' => 1, 'defender' => 2, 'midfielder' => 2, 'forward' => 2],
        ])->assertOk()->assertJsonPath('data.bench_size', 5);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['bench_size' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('bench_size');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'bench_role_limits' => [...LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS, 'winger' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('bench_role_limits');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'bench_role_limits' => [...LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS, 'goalkeeper' => -1],
        ])->assertUnprocessable()->assertJsonValidationErrors('bench_role_limits.goalkeeper');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'bench_role_limits' => ['goalkeeper' => 0, 'defender' => 1, 'midfielder' => 1, 'forward' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('bench_role_limits');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['bench_size' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('max_substitutions');
    }

    public function test_substitution_rules_are_bounded_and_boolean_is_strict(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        foreach (LeagueSetting::SUBSTITUTION_ORDER_MODES as $mode) {
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'max_substitutions' => 7,
                'substitution_order_mode' => $mode,
                'allow_formation_change_on_substitution' => true,
            ])->assertOk()->assertJsonPath('data.substitution_order_mode', $mode);
        }

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['max_substitutions' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('max_substitutions');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['max_substitutions' => 8])
            ->assertUnprocessable()->assertJsonValidationErrors('max_substitutions');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['substitution_order_mode' => 'random'])
            ->assertUnprocessable()->assertJsonValidationErrors('substitution_order_mode');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['allow_formation_change_on_substitution' => 'yes'])
            ->assertUnprocessable()->assertJsonValidationErrors('allow_formation_change_on_substitution');
    }

    public function test_captain_rule_accepts_boolean_updates(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['captain_enabled' => true])
            ->assertOk()->assertJsonPath('data.captain_enabled', true);
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['captain_enabled' => false])
            ->assertOk()->assertJsonPath('data.captain_enabled', false);
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['captain_enabled' => 'enabled'])
            ->assertUnprocessable()->assertJsonValidationErrors('captain_enabled');
    }

    public function test_formation_and_bench_limits_must_be_compatible_with_roster_limits(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'allowed_formation_module_names' => ['4-3-3'],
        ])->assertOk();

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'roster_role_limits' => ['goalkeeper' => 3, 'defender' => 3, 'midfielder' => 10, 'forward' => 9],
        ])->assertUnprocessable()->assertJsonValidationErrors('allowed_formation_module_names');

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'bench_role_limits' => ['goalkeeper' => 1, 'defender' => 9, 'midfielder' => 3, 'forward' => 3],
        ])->assertUnprocessable()->assertJsonValidationErrors('bench_role_limits.defender');
    }

    private function leagueWithMember(string $role): array
    {
        $league = League::factory()->create();
        app(LeagueSettingsService::class)->initializeDefaults($league);
        $user = User::factory()->create();
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);

        return [$league, $user];
    }

    /** @return list<string> */
    private function lineupSettingKeys(): array
    {
        return [
            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
            LeagueSetting::BENCH_SIZE,
            LeagueSetting::BENCH_ROLE_LIMITS,
            LeagueSetting::MAX_SUBSTITUTIONS,
            LeagueSetting::SUBSTITUTION_ORDER_MODE,
            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
            LeagueSetting::CAPTAIN_ENABLED,
        ];
    }
}
