<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\League;
use App\Models\Matchday;
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
        $currentId = $matchdays->first(
            fn(Matchday $matchday): bool => $league->isCurrentFormationMatchday($matchday)
        )?->id;

        $matchdays->each(function (Matchday $matchday) use ($league, $currentId): void {
            $matchday->setAttribute('championship_state', match (true) {
                $matchday->ends_at->isPast() => 'past',
                $matchday->id === $currentId => 'current',
                default => 'upcoming',
            });
            $matchday->setAttribute('formation_allowed', $league->allowsFormationFor($matchday));
        });

        return MatchdayResource::collection($matchdays);
    }
}
