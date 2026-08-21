<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\FantasyMatchResult;
use App\Models\League;
use App\Models\LeagueMatchdayCalculation;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
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
            self::addCalculationCapabilities($matchday, $league, request()->user());
        });

        return MatchdayResource::collection($matchdays);
    }

    public static function addCalculationCapabilities(Matchday $matchday, League $league, ?User $user): void
    {
        $calculated = LeagueMatchdayCalculation::query()
            ->where('league_id', $league->id)->where('matchday_id', $matchday->id)->exists()
            || TeamMatchdayScore::query()
            ->where('league_id', $league->id)->where('matchday_id', $matchday->id)->exists()
            || FantasyMatchResult::query()->whereHas('fantasyMatch', fn($query) => $query
                ->where('league_id', $league->id)->where('matchday_id', $matchday->id))->exists();
        $authorized = $user !== null && $user->can('calculateMatchday', $league);
        $ended = now()->gte($matchday->ends_at);

        $matchday->setAttribute('is_calculated', $calculated);
        $matchday->setAttribute('can_calculate', $authorized && $ended && ! $calculated);
        $matchday->setAttribute('can_recalculate', $authorized && $ended && $calculated);
    }
}
