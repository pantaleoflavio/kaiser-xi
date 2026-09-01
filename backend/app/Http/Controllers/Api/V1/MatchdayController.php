<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\League;
use App\Models\Matchday;
use App\Services\Matchday\LeagueMatchdayCalculationService;
use App\Models\User;
use App\Services\League\ChampionshipMatchdays;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchdayController extends Controller
{
    public function index(League $league, ChampionshipMatchdays $championshipMatchdays): AnonymousResourceCollection
    {
        $query = match (true) {
            $league->isNonHeadToHeadChampionship() && $league->hasInitializedChampionship() => $championshipMatchdays->query($league),
            $league->isFormulaOne() => Matchday::query()->whereRaw('1 = 0'),
            default => Matchday::query()->where('season_id', $league->season_id),
        };
        $matchdays = $query->orderBy('number')->get();
        $matchdays->each(function (Matchday $matchday) use ($league): void {
            $matchday->setAttribute('championship_state', $matchday->temporalState());
            $matchday->setAttribute('formation_allowed', $league->allowsFormationFor($matchday));
            self::addCalculationCapabilities($matchday, $league, request()->user());
        });

        return MatchdayResource::collection($matchdays);
    }

    public static function addCalculationCapabilities(Matchday $matchday, League $league, ?User $user): void
    {
        $authorized = $user !== null && $user->can('calculateMatchday', $league);
        foreach (app(LeagueMatchdayCalculationService::class)->capabilities($league, $matchday, $authorized) as $key => $value) {
            $matchday->setAttribute($key, $value);
        }
    }
}
