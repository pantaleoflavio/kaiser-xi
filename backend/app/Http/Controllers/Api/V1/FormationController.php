<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Formation\SaveFormationRequest;
use App\Http\Resources\Formation\FormationResource;
use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Services\Formation\SaveFormationService;
use App\Services\Formation\SubmitFormationService;

class FormationController extends Controller
{
    public function __construct(private SaveFormationService $saveService, private SubmitFormationService $submitService) {}

    public function show(League $league, Matchday $matchday, FantasyTeam $fantasyTeam): FormationResource
    {
        $this->assertContext($league, $matchday, $fantasyTeam);
        $formation = Formation::query()->where('league_id', $league->id)->where('fantasy_team_id', $fantasyTeam->id)->where('matchday_id', $matchday->id)->firstOrFail();

        return new FormationResource($formation->load($this->saveService->relations()));
    }

    public function update(SaveFormationRequest $request, League $league, Matchday $matchday, FantasyTeam $fantasyTeam): FormationResource
    {
        return new FormationResource($this->saveService->save($league, $matchday, $fantasyTeam, $request->validated()));
    }

    public function submit(League $league, Matchday $matchday, FantasyTeam $fantasyTeam): FormationResource
    {
        $this->assertContext($league, $matchday, $fantasyTeam);
        $formation = Formation::query()->where('league_id', $league->id)->where('fantasy_team_id', $fantasyTeam->id)->where('matchday_id', $matchday->id)->firstOrFail();

        return new FormationResource($this->submitService->submit($formation, $matchday));
    }

    private function assertContext(League $league, Matchday $matchday, FantasyTeam $team): void
    {
        abort_unless($team->league_id === $league->id && $matchday->season_id === $league->season_id, 404);
    }
}