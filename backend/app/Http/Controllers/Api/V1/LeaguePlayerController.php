<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\ListLeaguePlayersRequest;
use App\Http\Resources\League\LeaguePlayerResource;
use App\Models\League;
use App\Models\PlayerRole;
use App\Models\SeasonClub;
use App\Services\League\MarketPlayerQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaguePlayerController extends Controller
{
    public function __invoke(ListLeaguePlayersRequest $request, League $league, MarketPlayerQuery $players): AnonymousResourceCollection
    {
        return LeaguePlayerResource::collection(
            $players->query($league, $request->validated())->paginate((int) $request->input('per_page', 25)),
        )->additional(['filter_options' => [
            'clubs' => SeasonClub::query()
                ->where('season_id', $league->season_id)
                ->with('realClub:id,name')
                ->get(['id', 'real_club_id', 'display_name'])
                ->map(fn(SeasonClub $club): array => [
                    'id' => $club->real_club_id,
                    'name' => $club->display_name ?? $club->realClub->name,
                ])->sortBy('name')->values(),
            'positions' => PlayerRole::query()->orderBy('sort_order')->get(['key', 'label']),
        ]]);
    }
}
