<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\League\StandingResource;
use App\Models\League;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StandingController extends Controller
{
    public function index(League $league): AnonymousResourceCollection
    {
        $standings = $league->standings()
            ->with(['fantasyTeam:id,name,slug', 'league.type'])
            ->orderBy('position')
            ->orderBy('fantasy_team_id')
            ->get();

        return StandingResource::collection($standings);
    }
}
