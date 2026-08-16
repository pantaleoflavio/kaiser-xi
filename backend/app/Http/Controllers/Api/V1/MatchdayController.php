<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\League;
use App\Models\Matchday;
use App\Services\League\ClassicChampionshipMatchdays;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchdayController extends Controller
{
    public function index(League $league, ClassicChampionshipMatchdays $classicMatchdays): AnonymousResourceCollection
    {
        $query = $league->isClassic() && $league->hasInitializedClassicChampionship()
            ? $classicMatchdays->query($league)
            : Matchday::query()->where('season_id', $league->season_id);
        $matchdays = $query->orderBy('number')->get();
        $currentId = $matchdays->first(fn(Matchday $matchday): bool => $matchday->ends_at->isFuture())?->id;

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
