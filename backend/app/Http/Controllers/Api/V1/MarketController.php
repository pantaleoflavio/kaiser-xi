<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueStatus;
use App\Services\League\MarketAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function __invoke(Request $request, League $league, MarketAvailability $availability): JsonResponse
    {
        $open = $availability->isOpen($league);
        $canManage = $request->user()->can('manageSettings', $league)
            && ! in_array($league->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true);
        $hasTeam = FantasyTeam::query()->where('league_id', $league->id)->where('user_id', $request->user()->id)->exists();
        return response()->json(['data' => [
            'enabled' => $league->tradeMarketEnabled(),
            'is_open' => $open,
            'opens_at' => $league->tradeMarketOpensAt(),
            'closes_at' => $league->tradeMarketClosesAt(),
            'cash_adjustment_enabled' => $league->tradeCashAdjustmentEnabled(),
            'can_manage' => $canManage,
            'can_trade' => $open && $hasTeam,
        ]]);
    }
}
