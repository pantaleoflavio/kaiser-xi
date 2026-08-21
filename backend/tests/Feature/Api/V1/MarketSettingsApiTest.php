<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_managers_can_persist_market_settings_idempotently_and_participants_cannot(): void
    {
        $payload = ['trade_market_enabled' => true, 'trade_market_opens_at' => '2026-09-01T10:00:00Z', 'trade_market_closes_at' => '2026-09-01T12:00:00Z', 'trade_cash_adjustment_enabled' => false];
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $member] = $this->leagueWithMember($role);
            Sanctum::actingAs($member);
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", $payload)->assertOk()->assertJsonPath('data.trade_market_enabled', true)->assertJsonPath('data.trade_market_opens_at', $payload['trade_market_opens_at'])->assertJsonPath('data.trade_cash_adjustment_enabled', false);
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", $payload)->assertOk();
            $this->assertSame(4, $league->settings()->whereIn('key', array_keys($payload))->count());
        }

        [$league, $participant] = $this->leagueWithMember('participant');
        Sanctum::actingAs($participant);
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", $payload)->assertForbidden();
    }

    public function test_market_setting_types_and_window_are_validated(): void
    {
        [$league, $manager] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($manager);
        $url = "/api/v1/leagues/{$league->id}/settings";

        $this->patchJson($url, ['trade_market_enabled' => false, 'trade_cash_adjustment_enabled' => true])->assertOk();
        $this->patchJson($url, ['trade_market_enabled' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('trade_market_enabled');
        $this->patchJson($url, ['trade_cash_adjustment_enabled' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('trade_cash_adjustment_enabled');
        $this->patchJson($url, ['trade_market_opens_at' => 'not-a-date'])->assertUnprocessable()->assertJsonValidationErrors('trade_market_opens_at');
        $this->patchJson($url, ['trade_market_opens_at' => '2026-09-01T12:00:00Z', 'trade_market_closes_at' => '2026-09-01T12:00:00Z'])->assertUnprocessable()->assertJsonValidationErrors('trade_market_closes_at');
    }

    private function leagueWithMember(string $role): array
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_user_id' => $role === 'commissioner' ? $user->id : User::factory()]);
        $league->users()->attach($user, ['league_role_id' => LeagueRole::where('key', $role)->firstOrFail()->id, 'joined_at' => now()]);

        return [$league, $user];
    }
}
