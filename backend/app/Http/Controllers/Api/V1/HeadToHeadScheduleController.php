<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\League\InitializeHeadToHeadScheduleRequest;
use App\Http\Resources\League\HeadToHeadScheduleResource;
use App\Models\League;
use App\Services\League\GenerateHeadToHeadSchedule;

class HeadToHeadScheduleController extends Controller
{
    public function show(League $league): HeadToHeadScheduleResource
    {
        return new HeadToHeadScheduleResource($this->loadSchedule($league));
    }

    public function store(
        InitializeHeadToHeadScheduleRequest $request,
        League $league,
        GenerateHeadToHeadSchedule $service,
    ): HeadToHeadScheduleResource {
        $league = $service->handle($league, $request->integer('start_matchday_id'));

        return new HeadToHeadScheduleResource($this->loadSchedule($league));
    }

    private function loadSchedule(League $league): League
    {
        return $league->loadCount('fantasyTeams')->load([
            'h2hStartMatchday',
            'fantasyMatches' => fn($query) => $query
                ->with(['matchday', 'homeFantasyTeam', 'awayFantasyTeam'])
                ->orderBy('matchday_id')
                ->orderBy('id'),
        ]);
    }
}
