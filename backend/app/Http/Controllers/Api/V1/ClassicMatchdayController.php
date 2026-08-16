<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use Illuminate\Http\JsonResponse;

class ClassicMatchdayController extends Controller
{
    public function show(League $league, Matchday $matchday): JsonResponse
    {
        abort_unless(
            $league->isClassic()
                && $league->hasInitializedClassicChampionship()
                && $matchday->season_id === $league->season_id
                && $league->classic_start_matchday_id !== null
                && $matchday->starts_at->greaterThanOrEqualTo($league->classicStartMatchday()->value('starts_at')),
            404,
        );

        $formations = Formation::query()
            ->where('league_id', $league->id)
            ->where('matchday_id', $matchday->id)
            ->whereNotNull('submitted_at')
            ->get()
            ->keyBy('fantasy_team_id');
        $scores = TeamMatchdayScore::query()
            ->where('league_id', $league->id)
            ->where('matchday_id', $matchday->id)
            ->get()
            ->keyBy('fantasy_team_id');
        $counted = $matchday->ends_at->lessThanOrEqualTo(now());

        $teams = $league->classicParticipants()->orderBy('fantasy_teams.id')->get()->map(
            function ($team) use ($formations, $scores, $counted): array {
                $formation = $formations->get($team->id);
                $score = $scores->get($team->id);

                return [
                    'fantasy_team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                    ],
                    'formation_submitted' => $formation !== null,
                    'formation_id' => $formation?->id,
                    'result_status' => match (true) {
                        $score !== null => 'calculated',
                        $counted && $formation === null => 'missing_formation',
                        default => 'pending',
                    },
                    'points' => $score?->points ?? ($counted && $formation === null ? '0.00' : null),
                ];
            }
        )->values();

        return response()->json([
            'data' => [
                'matchday' => [
                    'id' => $matchday->id,
                    'number' => $matchday->number,
                    'name' => $matchday->name,
                    'counted' => $counted,
                ],
                'teams' => $teams,
            ],
        ]);
    }
}
