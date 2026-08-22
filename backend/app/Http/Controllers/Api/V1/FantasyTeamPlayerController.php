<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FantasyTeam\AddFantasyTeamPlayerRequest;
use App\Http\Resources\FantasyTeam\FantasyTeamPlayerResource;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\Player;
use App\Services\FantasyTeam\FantasyRosterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FantasyTeamPlayerController extends Controller
{
    public function __construct(private FantasyRosterService $rosterService) {}

    public function index(League $league, FantasyTeam $fantasyTeam): AnonymousResourceCollection
    {
        abort_unless($fantasyTeam->league_id === $league->id, 404);
        return FantasyTeamPlayerResource::collection(
            $fantasyTeam->activePlayerAssignments()
                ->with(['player.playerSeasonRegistrations' => fn($query) => $query
                    ->activeForSeason($league->season_id)
                    ->with('playerRole')])
                ->orderBy('assigned_at')
                ->get()
        );
    }

    public function store(AddFantasyTeamPlayerRequest $request, League $league, FantasyTeam $fantasyTeam): FantasyTeamPlayerResource
    {
        $assignment = $this->rosterService->assign(
            $league,
            $fantasyTeam,
            Player::query()->findOrFail($request->integer('player_id')),
            $request->user(),
            $request->integer('purchase_price')
        );

        return new FantasyTeamPlayerResource($this->loadSeasonRole($assignment, $league));
    }

    public function destroy(Request $request, League $league, FantasyTeam $fantasyTeam, Player $player): FantasyTeamPlayerResource
    {
        $assignment = $this->rosterService->release($league, $fantasyTeam, $player, $request->user());

        return new FantasyTeamPlayerResource($this->loadSeasonRole($assignment, $league));
    }

    private function loadSeasonRole(FantasyTeamPlayer $assignment, League $league): FantasyTeamPlayer
    {
        return $assignment->load(['player.playerSeasonRegistrations' => fn($query) => $query
            ->activeForSeason($league->season_id)
            ->with('playerRole')]);
    }
}
