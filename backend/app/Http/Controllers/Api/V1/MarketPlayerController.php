<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\ListMarketPlayersRequest;
use App\Http\Resources\Market\MarketPlayerResource;
use App\Models\League;
use App\Services\League\MarketPlayerQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketPlayerController extends Controller
{
    public function __invoke(ListMarketPlayersRequest $request, League $league, MarketPlayerQuery $players): AnonymousResourceCollection
    {
        return MarketPlayerResource::collection($players->query($league, $request->validated())->paginate((int) $request->input('per_page', 25)));
    }
}
