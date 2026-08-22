<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\ListEligiblePlayersRequest;
use App\Http\Resources\Player\EligiblePlayerResource;
use App\Models\League;
use App\Services\FantasyTeam\EligiblePlayerQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EligiblePlayerController extends Controller
{
    public function __construct(private EligiblePlayerQueryService $eligiblePlayers) {}

    public function index(ListEligiblePlayersRequest $request, League $league): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 15);

        return EligiblePlayerResource::collection(
            $this->eligiblePlayers->query($league, $request->validated())->paginate($perPage)
        );
    }
}
