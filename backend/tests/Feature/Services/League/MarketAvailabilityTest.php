<?php

namespace Tests\Feature\Services\League;

use App\Models\League;
use App\Models\LeagueSetting;
use App\Services\League\MarketAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_market_uses_an_inclusive_open_and_exclusive_close_boundary(): void
    {
        $league = League::factory()->create();
        $this->setting($league, LeagueSetting::TRADE_MARKET_ENABLED, LeagueSetting::booleanPayload(true));
        $this->setting($league, LeagueSetting::TRADE_MARKET_OPENS_AT, LeagueSetting::stringPayload('2026-08-21T10:00:00Z'));
        $this->setting($league, LeagueSetting::TRADE_MARKET_CLOSES_AT, LeagueSetting::stringPayload('2026-08-21T12:00:00Z'));
        $service = app(MarketAvailability::class);

        CarbonImmutable::setTestNow('2026-08-21T09:59:59Z');
        $this->assertFalse($service->isOpen($league));
        CarbonImmutable::setTestNow('2026-08-21T10:00:00Z');
        $this->assertTrue($service->isOpen($league));
        CarbonImmutable::setTestNow('2026-08-21T11:00:00Z');
        $this->assertTrue($service->isOpen($league));
        CarbonImmutable::setTestNow('2026-08-21T12:00:00Z');
        $this->assertFalse($service->isOpen($league));
        CarbonImmutable::setTestNow('2026-08-21T12:00:01Z');
        $this->assertFalse($service->isOpen($league));
    }

    public function test_disabled_or_unconfigured_market_is_closed(): void
    {
        $league = League::factory()->create();
        $this->assertFalse(app(MarketAvailability::class)->isOpen($league));
        $this->setting($league, LeagueSetting::TRADE_MARKET_ENABLED, LeagueSetting::booleanPayload(true));
        $this->assertFalse(app(MarketAvailability::class)->isOpen($league));
    }

    private function setting(League $league, string $key, array $value): void
    {
        $league->settings()->create(compact('key', 'value'));
    }
}
