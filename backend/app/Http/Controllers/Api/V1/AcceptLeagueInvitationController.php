<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\League\LeagueMemberResource;
use App\Http\Resources\LeagueInvitation\LeagueInvitationPreviewResource;
use App\Http\Resources\LeagueInvitation\LeagueInvitationResource;
use App\Models\LeagueInvitation;
use App\Services\LeagueInvitation\AcceptLeagueInvitationAction;
use App\Services\LeagueInvitation\RejectLeagueInvitationAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcceptLeagueInvitationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeagueInvitation::query()
            ->where('invited_user_id', $request->user()->id)
            ->with(['league', 'createdBy', 'role'])
            ->latest();
        $status = $request->string('status', 'pending')->toString();
        if ($status === 'expired') {
            $query->where('status', 'pending')->where('expires_at', '<=', now());
        } else {
            abort_unless(in_array($status, ['pending', 'accepted', 'rejected', 'revoked'], true), 422, 'Invalid status filter.');
            $query->where('status', $status);
            if ($status === 'pending') {
                $query->where(fn($value) => $value->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            }
        }
        return LeagueInvitationResource::collection($query->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(Request $request, string $code): LeagueInvitationPreviewResource
    {
        $invitation = $this->ownedByCode($code, $request)->load(['league.season.realCompetition', 'league.type', 'league.status', 'league.memberships', 'createdBy', 'role']);
        $invitation->league->loadCount('memberships');
        return new LeagueInvitationPreviewResource($invitation);
    }

    public function accept(Request $request, LeagueInvitation $invitation, AcceptLeagueInvitationAction $action): LeagueMemberResource
    {
        $this->ensureOwner($request, $invitation);
        return new LeagueMemberResource($action->handle($invitation, $request->user()));
    }

    public function acceptCode(Request $request, string $code, AcceptLeagueInvitationAction $action): LeagueMemberResource
    {
        $invitation = $this->ownedByCode($code, $request);
        return new LeagueMemberResource($action->handle($invitation, $request->user()));
    }

    public function reject(Request $request, LeagueInvitation $invitation, RejectLeagueInvitationAction $action): LeagueInvitationResource
    {
        $this->ensureOwner($request, $invitation);
        return new LeagueInvitationResource($action->handle($invitation)->load(['league', 'createdBy', 'role']));
    }

    private function ownedByCode(string $code, Request $request): LeagueInvitation
    {
        $invitation = LeagueInvitation::query()->where('code', $code)->firstOrFail();
        $this->ensureOwner($request, $invitation);
        return $invitation;
    }

    private function ensureOwner(Request $request, LeagueInvitation $invitation): void
    {
        abort_unless($invitation->invited_user_id === $request->user()->id, 404);
    }
}
