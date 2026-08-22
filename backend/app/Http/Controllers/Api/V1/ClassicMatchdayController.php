<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Services\League\ChampionshipMatchdays;
use App\Services\Standings\CalculateFormulaOneStandings;
use Illuminate\Http\JsonResponse;

class ClassicMatchdayController extends Controller
{
    public function show(League $league, Matchday $matchday, ChampionshipMatchdays $matchdays, CalculateFormulaOneStandings $formulaOne): JsonResponse
    {
        abort_unless($matchdays->contains($league, $matchday), 404);

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

        $placements = $counted && $league->isFormulaOne()
            ? $formulaOne->placementsFor($league, $matchday->id)->keyBy('fantasyTeamId')
            : collect();
        $teams = $league->championshipParticipants()->orderBy('fantasy_teams.id')->get()->map(
            function ($team) use ($formations, $scores, $counted, $placements): array {
                $formation = $formations->get($team->id);
                // Aggregate scores are historical output. Open/future matchdays
                // must report submission state without previewing a result.
                $score = $counted ? $scores->get($team->id) : null;

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
                    'finishing_position' => $placements->get($team->id)?->position,
                    'championship_points' => $placements->get($team->id)?->championshipPoints,
                ];
            }
        )->when($league->isFormulaOne() && $counted, fn($teams) => $teams->sortBy('finishing_position'))->values();

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
