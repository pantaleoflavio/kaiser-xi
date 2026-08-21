<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        CarbonImmutable::setTestNow('2026-08-21 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_members_can_read_market_but_a_non_member_cannot(): void
    {
        foreach (['commissioner', 'co_commissioner', 'participant'] as $role) {
            [$league, $member] = $this->leagueWithMember($role);
            Sanctum::actingAs($member);
            $this->getJson("/api/v1/leagues/{$league->id}/market")->assertOk();
            $this->getJson("/api/v1/leagues/{$league->id}/market/players")->assertOk();
        }

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/leagues/{$league->id}/market")->assertForbidden();
        $this->getJson("/api/v1/leagues/{$league->id}/market/players")->assertForbidden();
    }

    public function test_capabilities_are_backend_authoritative(): void
    {
        foreach (['commissioner', 'co_commissioner', 'participant'] as $role) {
            [$league, $member] = $this->leagueWithMember($role);
            $this->openMarket($league);
            FantasyTeam::factory()->forLeagueAndUser($league, $member)->create();
            Sanctum::actingAs($member);

            $this->getJson("/api/v1/leagues/{$league->id}/market")
                ->assertOk()
                ->assertJsonPath('data.can_manage', $role !== 'participant')
                ->assertJsonPath('data.can_trade', true);
        }

        [$league, $participant] = $this->leagueWithMember('participant');
        $this->openMarket($league);
        FantasyTeam::factory()->forLeagueAndUser(League::factory()->create(), $participant)->create();
        Sanctum::actingAs($participant);
        $this->getJson("/api/v1/leagues/{$league->id}/market")->assertJsonPath('data.can_trade', false);

        FantasyTeam::factory()->forLeagueAndUser($league, $participant)->create();
        $league->settings()->where('key', LeagueSetting::TRADE_MARKET_ENABLED)->update(['value' => LeagueSetting::booleanPayload(false)]);
        $this->getJson("/api/v1/leagues/{$league->id}/market")->assertJsonPath('data.can_trade', false);
    }

    private function openMarket(League $league): void
    {
        foreach (
            [
                LeagueSetting::TRADE_MARKET_ENABLED => LeagueSetting::booleanPayload(true),
                LeagueSetting::TRADE_MARKET_OPENS_AT => LeagueSetting::stringPayload('2026-08-21T10:00:00Z'),
                LeagueSetting::TRADE_MARKET_CLOSES_AT => LeagueSetting::stringPayload('2026-08-21T14:00:00Z'),
            ] as $key => $value
        ) {
            $league->settings()->create(compact('key', 'value'));
        }
    }

    private function leagueWithMember(string $role): array
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_user_id' => $role === 'commissioner' ? $user->id : User::factory()]);
        $league->users()->attach($user, ['league_role_id' => LeagueRole::where('key', $role)->firstOrFail()->id, 'joined_at' => now()]);

        return [$league, $user];
    }
}
