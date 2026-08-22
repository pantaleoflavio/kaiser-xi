<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LeagueRole;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueMembershipService
{
    public function remove(League $league, LeagueMembership $membership): void
    {
        DB::transaction(function () use ($league, $membership): void {
            $membership = $this->lockedMembership($league, $membership);

            if ($membership->user_id === $league->commissioner_user_id) {
                throw new ConflictHttpException('The league commissioner cannot be removed.');
            }

            $membership->delete();
        });
    }

    public function updateRole(League $league, LeagueMembership $membership, string $roleKey): LeagueMembership
    {
        return DB::transaction(function () use ($league, $membership, $roleKey): LeagueMembership {
            $membership = $this->lockedMembership($league, $membership);

            if ($membership->user_id === $league->commissioner_user_id) {
                throw new ConflictHttpException('The league commissioner role cannot be changed.');
            }

            $role = LeagueRole::query()->where('key', $roleKey)->firstOrFail();
            $membership->forceFill(['league_role_id' => $role->id])->save();

            return $membership->load(['role', 'user']);
        });
    }

    private function lockedMembership(League $league, LeagueMembership $membership): LeagueMembership
    {
        return $league->memberships()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
    }
}
