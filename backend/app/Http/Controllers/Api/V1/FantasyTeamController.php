<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FantasyTeam\StoreFantasyTeamRequest;
use App\Http\Requests\FantasyTeam\UpdateFantasyTeamRequest;
use App\Http\Resources\FantasyTeam\FantasyTeamResource;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Services\FantasyTeam\FantasyTeamService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FantasyTeamController extends Controller
{
    public function __construct(private FantasyTeamService $fantasyTeamService) {}

    public function index(League $league): AnonymousResourceCollection
    {
        return FantasyTeamResource::collection(
            $league->fantasyTeams()
                ->with('user')
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
        );
    }

    public function store(StoreFantasyTeamRequest $request, League $league): FantasyTeamResource
    {
        $fantasyTeam = $this->fantasyTeamService->create(
            $league,
            $request->user(),
            $request->validated('name')
        );

        return new FantasyTeamResource($fantasyTeam);
    }

    public function show(League $league, FantasyTeam $fantasyTeam): FantasyTeamResource
    {
        abort_unless($fantasyTeam->league_id === $league->id, 404);
        return new FantasyTeamResource($fantasyTeam->load('user'));
    }

    public function update(UpdateFantasyTeamRequest $request, League $league, FantasyTeam $fantasyTeam): FantasyTeamResource
    {
        abort_unless($fantasyTeam->league_id === $league->id, 404);
        $fantasyTeam = $this->fantasyTeamService
            ->update($fantasyTeam, $request->validated('name'))
            ->load('user');

        return new FantasyTeamResource($fantasyTeam);
    }
}
