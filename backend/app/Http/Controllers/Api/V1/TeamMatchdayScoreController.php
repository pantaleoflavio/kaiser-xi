<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scoring\TeamMatchdayResultResource;
use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;

class TeamMatchdayScoreController extends Controller
{
    public function show(League $league, Matchday $matchday, FantasyTeam $fantasyTeam): TeamMatchdayResultResource
    {
        $this->assertContext($league, $matchday, $fantasyTeam);

        $formation = Formation::query()
            ->whereBelongsTo($league)->whereBelongsTo($matchday)->whereBelongsTo($fantasyTeam)
            ->whereNotNull('submitted_at')
            ->with([
                'fantasyTeam',
                'matchday',
                'formationModule.requirements.playerRole',
                'players.player',
                'players.playerRole',
            ])->firstOrFail();

        $score = TeamMatchdayScore::query()
            ->whereBelongsTo($league)->whereBelongsTo($matchday)->whereBelongsTo($fantasyTeam)
            ->where('formation_id', $formation->id)
            ->with(['details.player', 'details.replacedPlayer', 'details.playerScore'])
            ->first();

        return new TeamMatchdayResultResource($formation, $score);
    }

    private function assertContext(League $league, Matchday $matchday, FantasyTeam $fantasyTeam): void
    {
        abort_unless(
            $fantasyTeam->league_id === $league->id && $matchday->season_id === $league->season_id,
            404,
        );
    }
}
