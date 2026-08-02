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
        return $this->hasAnyRole($user, $league, ['commissioner', 'co_commissioner']);
    }

    public function manageSettings(User $user, League $league): bool
    {
        return $this->hasAnyRole($user, $league, ['commissioner', 'co_commissioner']);
    }

    public function activate(User $user, League $league): bool
    {
        return $this->hasRole($user, $league, 'commissioner')
            && $league->commissioner_user_id === $user->id;
    }

    public function removeMember(User $user, League $league, User $target): bool
    {
        if ($user->is($target) || ! $this->isMember($target, $league)) {
            return false;
        }

        if ($this->hasRole($user, $league, 'commissioner')) {
            return $target->id !== $league->commissioner_user_id;
        }

        return $this->hasRole($user, $league, 'co_commissioner')
            && $this->hasRole($target, $league, 'participant');
    }

    public function manageMemberRole(User $user, League $league, User $target): bool
    {
        return ! $user->is($target)
            && $target->id !== $league->commissioner_user_id
            && $this->isMember($target, $league)
            && $this->hasRole($user, $league, 'commissioner');
    }

    public function delete(User $user, League $league): bool
    {
        return $this->hasRole($user, $league, 'commissioner');
    }

    private function isMember(User $user, League $league): bool
    {
        return $league->users()->whereKey($user->id)->exists();
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
