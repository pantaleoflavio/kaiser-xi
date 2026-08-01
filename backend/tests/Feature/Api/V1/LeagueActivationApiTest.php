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

class LeagueActivationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_commissioner_can_activate_valid_draft_league_once(): void
    {
        [$league, $commissioner] = $this->readyLeague();

        Sanctum::actingAs($commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status.key', LeagueStatus::ACTIVE);

        $this->assertSame(
            LeagueStatus::ACTIVE,
            $league->refresh()->statusKey()
        );

        $this->postJson("/api/v1/leagues/{$league->id}/activate")
            ->assertConflict()
            ->assertJsonPath('code', 'league_already_active');
    }

    public function test_only_commissioner_can_activate(): void
    {
        foreach (['co_commissioner', 'participant'] as $role) {
            [$league] = $this->readyLeague();
            $actor = User::factory()->create();
            $this->attach($league, $actor, $role);
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/leagues/{$league->id}/activate")->assertForbidden();
        }

        [$league] = $this->readyLeague();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/leagues/{$league->id}/activate")->assertForbidden();
    }

    public function test_incomplete_configuration_blocks_activation_without_changing_status(): void
    {
        [$league, $commissioner] = $this->readyLeague();
        $league->settings()->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)->delete();
        Sanctum::actingAs($commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/activate")
            ->assertUnprocessable()->assertJsonPath('code', 'league_configuration_incomplete');
        $this->assertSame(LeagueStatus::DRAFT, $league->refresh()->statusKey());
    }

    public function test_invalid_role_limit_sum_blocks_activation_transactionally(): void
    {
        [$league, $commissioner] = $this->readyLeague();
        $league->settings()->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)->update([
            'value' => LeagueSetting::roleLimitsPayload(array_fill_keys(LeagueSetting::PLAYER_ROLE_KEYS, 1)),
        ]);
        Sanctum::actingAs($commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/activate")->assertUnprocessable();
        $this->assertSame(LeagueStatus::DRAFT, $league->refresh()->statusKey());
    }

    public function test_completed_and_archived_leagues_cannot_be_activated(): void
    {
        foreach ([LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED] as $status) {
            [$league, $commissioner] = $this->readyLeague();
            $league->update(['league_status_id' => LeagueStatus::query()->where('key', $status)->value('id')]);
            Sanctum::actingAs($commissioner);
            $this->postJson("/api/v1/leagues/{$league->id}/activate")
                ->assertConflict()->assertJsonPath('code', 'league_activation_state_invalid');
        }
    }

    private function readyLeague(): array
    {
        $commissioner = User::factory()->create();

        $league = League::factory()->create([
            'commissioner_user_id' => $commissioner->id,
            'league_status_id' => LeagueStatus::query()
                ->where('key', LeagueStatus::DRAFT)
                ->value('id'),
        ]);

        $league->users()->attach($commissioner->id, [
            'league_role_id' => LeagueRole::query()
                ->where('key', 'commissioner')
                ->value('id'),
            'joined_at' => now(),
        ]);

        app(LeagueSettingsService::class)->initializeDefaults($league);

        return [$league->refresh(), $commissioner];
    }

    private function attach(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->value('id'),
            'joined_at' => now(),
        ]);
    }
}