<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\InitializeClassicChampionshipRequest;
use App\Http\Resources\League\ClassicChampionshipResource;
use App\Models\League;
use App\Services\League\InitializeClassicChampionship;

class ClassicChampionshipController extends Controller
{
    public function show(League $league): ClassicChampionshipResource
    {
        return new ClassicChampionshipResource($this->load($league));
    }

    public function store(InitializeClassicChampionshipRequest $request, League $league, InitializeClassicChampionship $service): ClassicChampionshipResource
    {
        return new ClassicChampionshipResource($this->load($service->handle($league, $request->integer('start_matchday_id'))));
    }

    private function load(League $league): League
    {
        return $league->load('classicStartMatchday')->loadCount(['fantasyTeams', 'classicParticipants']);
    }
}
