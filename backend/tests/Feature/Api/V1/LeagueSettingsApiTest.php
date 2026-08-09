<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueType;
use App\Models\Season;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_commissioner_and_co_commissioner_can_view_and_update_budget_settings(): void
    {
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $user] = $this->leagueWithMember($role);
            Sanctum::actingAs($user);

            $this->getJson("/api/v1/leagues/{$league->id}/settings")
                ->assertOk()
                ->assertJsonPath('data.initial_budget', 500)
                ->assertJsonPath('data.release_refund_percentage', 50);

            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'initial_budget' => 750,
                'release_refund_percentage' => 60,
            ])->assertOk()
                ->assertJsonPath('data.initial_budget', 750)
                ->assertJsonPath('data.release_refund_percentage', 60);
        }
    }

    public function test_new_league_persists_and_returns_default_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $season = Season::factory()->create();
        $type = LeagueType::query()->where('key', 'classic')->firstOrFail();

        $leagueId = $this->postJson('/api/v1/leagues', [
            'name' => 'Lifecycle defaults',
            'season_id' => $season->id,
            'league_type_id' => $type->id,
            'max_participants' => 10,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/leagues/{$leagueId}/settings")
            ->assertOk()
            ->assertJsonPath('data.initial_budget', LeagueSetting::DEFAULT_INITIAL_BUDGET)
            ->assertJsonPath('data.release_refund_percentage', LeagueSetting::DEFAULT_RELEASE_REFUND_PERCENTAGE)
            ->assertJsonPath('data.max_roster_players', LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.can_activate');

        foreach (
            [
                LeagueSetting::INITIAL_BUDGET,
                LeagueSetting::RELEASE_REFUND_PERCENTAGE,
                LeagueSetting::MAX_ROSTER_PLAYERS,
                LeagueSetting::ROSTER_ROLE_LIMITS,
                LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
                LeagueSetting::BENCH_SIZE,
                LeagueSetting::BENCH_ROLE_LIMITS,
                LeagueSetting::MAX_SUBSTITUTIONS,
                LeagueSetting::SUBSTITUTION_ORDER_MODE,
                LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
                LeagueSetting::CAPTAIN_ENABLED,
            ] as $key
        ) {
            $this->assertDatabaseHas('league_settings', ['league_id' => $leagueId, 'key' => $key]);
        }
    }

    public function test_participant_and_non_member_cannot_manage_settings(): void
    {
        [$league, $participant] = $this->leagueWithMember('participant');
        foreach ([$participant, User::factory()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['initial_budget' => 1])->assertForbidden();
        }
    }

    public function test_non_member_cannot_view_settings(): void
    {
        $league = League::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/leagues/{$league->id}/settings")->assertForbidden();
    }

    public function test_settings_validation(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => -1])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 101])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 12.5])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['initial_budget' => -1])->assertUnprocessable()->assertJsonValidationErrors('initial_budget');
    }

    public function test_commissioner_and_co_commissioner_can_update_roster_rules(): void
    {
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $user] = $this->leagueWithMember($role);
            Sanctum::actingAs($user);

            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'max_roster_players' => 20,
                'roster_role_limits' => [
                    'goalkeeper' => 2,
                    'defender' => 7,
                    'midfielder' => 7,
                    'forward' => 5,
                ],
            ])->assertOk()
                ->assertJsonPath('data.max_roster_players', 20)
                ->assertJsonPath('data.roster_role_limits.goalkeeper', 2);
        }
    }

    public function test_roster_rule_shape_and_ranges_are_validated(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $valid = LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS;
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['max_roster_players' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('max_roster_players');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['roster_role_limits' => [...$valid, 'winger' => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('roster_role_limits');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['roster_role_limits' => [...$valid, 'goalkeeper' => -1]])
            ->assertUnprocessable()->assertJsonValidationErrors('roster_role_limits.goalkeeper');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['roster_role_limits' => [1 => 25] + $valid])
            ->assertUnprocessable()->assertJsonValidationErrors('roster_role_limits');

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'roster_role_limits' => [...$valid, 'goalkeeper' => 0, 'forward' => 9],
        ])->assertUnprocessable()->assertJsonValidationErrors('allowed_formation_module_names');
    }

    public function test_role_limit_sum_cannot_be_lower_than_persisted_or_submitted_maximum(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        app(LeagueSettingsService::class)->initializeDefaults($league);
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
            'roster_role_limits' => ['goalkeeper' => 1, 'defender' => 1, 'midfielder' => 1, 'forward' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors('roster_role_limits');

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['max_roster_players' => 26])
            ->assertUnprocessable()->assertJsonValidationErrors('roster_role_limits');

        $this->assertSame(25, $league->refresh()->maxRosterPlayers());
    }

    public function test_new_league_api_initializes_persisted_roster_defaults(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $season = Season::factory()->create();
        $type = LeagueType::query()->where('key', 'classic')->firstOrFail();

        $response = $this->postJson('/api/v1/leagues', [
            'name' => 'Roster defaults league',
            'season_id' => $season->id,
            'league_type_id' => $type->id,
            'max_participants' => 10,
        ])->assertCreated();

        $league = League::query()->findOrFail($response->json('data.id'));
        $this->assertSame(25, $league->maxRosterPlayers());
        $this->assertSame(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS, $league->rosterRoleLimits());
        $this->assertDatabaseHas('league_settings', ['league_id' => $league->id, 'key' => LeagueSetting::MAX_ROSTER_PLAYERS]);
        $this->assertDatabaseHas('league_settings', ['league_id' => $league->id, 'key' => LeagueSetting::ROSTER_ROLE_LIMITS]);
    }

    public function test_demo_league_roster_settings_are_seeded_idempotently(): void
    {
        $this->seed(DemoLeagueSeeder::class);
        $this->seed(DemoLeagueSeeder::class);
        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();

        $this->assertSame(1, $league->settings()->where('key', LeagueSetting::MAX_ROSTER_PLAYERS)->count());
        $this->assertSame(1, $league->settings()->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)->count());
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
