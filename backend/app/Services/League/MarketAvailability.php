<?php

namespace App\Services\League;

use App\Models\League;
use Carbon\CarbonImmutable;

class MarketAvailability
{
    public function isOpen(League $league): bool
    {
        if (! $league->tradeMarketEnabled() || ! $league->tradeMarketOpensAt() || ! $league->tradeMarketClosesAt()) {
            return false;
        }

        $now = CarbonImmutable::now();
        $opensAt = CarbonImmutable::parse($league->tradeMarketOpensAt());
        $closesAt = CarbonImmutable::parse($league->tradeMarketClosesAt());

        return $now->greaterThanOrEqualTo($opensAt) && $now->lessThan($closesAt);
    }
}
