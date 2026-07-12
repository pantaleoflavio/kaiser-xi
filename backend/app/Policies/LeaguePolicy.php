<?php

namespace App\Policies;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\User;

class LeaguePolicy
{
    public function view(User $user, League $league): bool
    {
        return $league->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, League $league): bool
    {
        return $this->hasRole($user, $league, 'commissioner');
    }

    public function manageInvitations(User $user, League $league): bool
    {
        return $this->hasRole($user, $league, 'commissioner');
    }

    public function manageSettings(User $user, League $league): bool
    {
        return $this->hasAnyRole($user, $league, ['commissioner', 'co_commissioner']);
    }

    public function delete(User $user, League $league): bool
    {
        return $this->hasRole($user, $league, 'commissioner');
    }

    private function hasRole(User $user, League $league, string $role): bool
    {
        return $this->hasAnyRole($user, $league, [$role]);
    }

    private function hasAnyRole(User $user, League $league, array $roles): bool
    {
        return $league->users()
            ->whereKey($user->id)
            ->wherePivotIn(
                'league_role_id',
                LeagueRole::query()->whereIn('key', $roles)->pluck('id')
            )
            ->exists();
    }
}
