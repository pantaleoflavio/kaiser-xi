<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\League;
use App\Models\Matchday;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchdayController extends Controller
{
    public function index(League $league): AnonymousResourceCollection
    {
        return MatchdayResource::collection(Matchday::query()->where('season_id', $league->season_id)->orderBy('number')->get());
    }
}