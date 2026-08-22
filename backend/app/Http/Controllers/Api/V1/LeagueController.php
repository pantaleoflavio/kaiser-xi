<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\StoreLeagueRequest;
use App\Http\Requests\League\UpdateLeagueRequest;
use App\Http\Resources\League\LeagueResource;
use App\Models\League;
use App\Services\League\CreateLeague;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeagueController extends Controller
{
    private const WITH = [
        'season.realCompetition',
        'type',
        'status',
        'memberships.role',
    ];

    public function index(): AnonymousResourceCollection
    {
        return LeagueResource::collection(
            request()->user()->leagues()->with(self::WITH)->paginate()
        );
    }

    public function store(StoreLeagueRequest $request, CreateLeague $createLeague): JsonResponse
    {
        $league = $createLeague
            ->handle($request->validated(), $request->user())
            ->load(self::WITH);

        return (new LeagueResource($league))
            ->response()
            ->setStatusCode(201);
    }

    public function show(League $league): LeagueResource
    {
        return new LeagueResource($league->load(self::WITH));
    }

    public function update(UpdateLeagueRequest $request, League $league): LeagueResource
    {
        $league->update($request->validated());

        return new LeagueResource($league->load(self::WITH));
    }

    public function destroy(League $league): JsonResponse
    {
        $league->delete();

        return response()->json(null, 204);
    }
}
