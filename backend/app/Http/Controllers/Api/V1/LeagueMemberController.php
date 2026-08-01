<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\UpdateLeagueMemberRoleRequest;
use App\Http\Resources\League\LeagueMemberResource;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use App\Services\League\LeagueMembershipService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LeagueMemberController extends Controller
{
    public function __construct(private LeagueMembershipService $membershipService) {}

    public function index(League $league): AnonymousResourceCollection
    {
        return LeagueMemberResource::collection(
            $league->memberships()->with(['role', 'user'])->paginate()
        );
    }

    public function destroy(League $league, User $user): Response
    {
        $membership = $this->membership($league, $user);
        $this->membershipService->remove($league, $membership);
        return response()->noContent();
    }

    public function updateRole(UpdateLeagueMemberRoleRequest $request, League $league, User $user): LeagueMemberResource
    {
        $membership = $this->membership($league, $user);
        return new LeagueMemberResource(
            $this->membershipService->updateRole($league, $membership, $request->validated('role'))
        );
    }

    private function membership(League $league, User $user): LeagueMembership
    {
        return $league->memberships()->where('user_id', $user->id)->firstOrFail();
    }
}
