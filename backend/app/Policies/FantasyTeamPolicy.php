<?php

namespace App\Policies;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\User;

class FantasyTeamPolicy
{
    public function viewAny(User $user, League $league): bool
    {
        return $league->users()->whereKey($user->id)->exists();
    }

    public function create(User $user, League $league): bool
    {
        return $league->users()->whereKey($user->id)->exists();
    }

    public function view(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->league->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id
            && $fantasyTeam->league->users()->whereKey($user->id)->exists();
    }

    public function viewRoster(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $this->view($user, $fantasyTeam);
    }

    public function manageRoster(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $this->update($user, $fantasyTeam);
    }
}
