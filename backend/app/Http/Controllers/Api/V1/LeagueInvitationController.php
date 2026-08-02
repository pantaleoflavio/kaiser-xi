<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LeagueInvitationCapacityExceeded;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeagueInvitation\StoreLeagueInvitationRequest;
use App\Http\Resources\LeagueInvitation\LeagueInvitationResource;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Services\LeagueInvitation\CancelLeagueInvitationAction;
use App\Services\LeagueInvitation\CreateLeagueInvitationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeagueInvitationController extends Controller
{
    public function index(League $league): AnonymousResourceCollection
    {
        return LeagueInvitationResource::collection(
            $league->invitations()->with(['createdBy', 'invitedUser', 'role'])->latest()->paginate()
        );
    }

    public function store(StoreLeagueInvitationRequest $request, League $league, CreateLeagueInvitationAction $action): JsonResponse
    {
        try {
            $invitation = $action->handle($league, $request->user(), $request->validated())->load(['createdBy', 'invitedUser', 'role']);
        } catch (LeagueInvitationCapacityExceeded $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['max_uses' => [$exception->getMessage()]],
            ], 422);
        }

        return (new LeagueInvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroy(League $league, LeagueInvitation $invitation, CancelLeagueInvitationAction $action): JsonResponse
    {
        $action->handle($invitation);

        return response()->json(null, 204);
    }
}
