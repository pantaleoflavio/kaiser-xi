<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TradeConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Market\StoreTradeProposalRequest;
use App\Http\Resources\Market\TradeProposalResource;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\TradeProposal;
use App\Services\Market\TradeProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketTradeController extends Controller
{
    public function index(Request $request, League $league): AnonymousResourceCollection
    {
        $team = FantasyTeam::query()->where('league_id', $league->id)->where('user_id', $request->user()->id)->first();
        $query = TradeProposal::query()->where('league_id', $league->id);
        if (! $request->user()->can('manageSettings', $league)) $query->where(fn($q) => $q->where('from_team_id', $team?->id ?? 0)->orWhere('to_team_id', $team?->id ?? 0));
        return TradeProposalResource::collection($query->latest()->with($this->relations())->get());
    }

    public function store(StoreTradeProposalRequest $request, League $league, TradeProposalService $service): JsonResponse
    {
        return $this->respond(fn() => $service->propose($league, $request->user(), $request->validated()), 201);
    }

    public function accept(Request $request, League $league, TradeProposal $tradeProposal, TradeProposalService $service): JsonResponse
    {
        abort_unless($tradeProposal->league_id === $league->id, 404);
        return $this->respond(fn() => $service->accept($league, $tradeProposal, $request->user()));
    }
    public function reject(Request $request, League $league, TradeProposal $tradeProposal, TradeProposalService $service): JsonResponse
    {
        abort_unless($tradeProposal->league_id === $league->id, 404);
        return $this->respond(fn() => $service->reject($tradeProposal, $request->user()));
    }
    public function cancel(Request $request, League $league, TradeProposal $tradeProposal, TradeProposalService $service): JsonResponse
    {
        abort_unless($tradeProposal->league_id === $league->id, 404);
        return $this->respond(fn() => $service->cancel($tradeProposal, $request->user()));
    }

    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            $trade = $action();
        } catch (TradeConflictException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode], 409);
        }
        abort_unless($trade->league_id, 404);
        return (new TradeProposalResource($trade->load($this->relations())))->response()->setStatusCode($status);
    }

    private function relations(): array
    {
        return ['fromTeam', 'toTeam', 'cashPaidByTeam', 'offeredAssignment.player', 'requestedAssignment.player'];
    }
}
