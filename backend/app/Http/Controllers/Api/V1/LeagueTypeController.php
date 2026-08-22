<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\League\LeagueTypeResource;
use App\Models\LeagueType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeagueTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LeagueTypeResource::collection(
            LeagueType::query()->orderBy('key')->orderBy('id')->get()
        );
    }
}
