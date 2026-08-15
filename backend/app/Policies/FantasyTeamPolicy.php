<?php

namespace App\Policies;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Matchday;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    public function manageRoster(User $user, FantasyTeam $fantasyTeam, League $league): bool
    {
        if ($fantasyTeam->league_id !== $league->id) {
            return false;
        }

        return $league->users()
            ->whereKey($user->id)
            ->wherePivotIn(
                'league_role_id',
                LeagueRole::query()->whereIn('key', ['commissioner', 'co_commissioner'])->pluck('id')
            )
            ->exists();
    }

    public function viewFormation(User $user, FantasyTeam $fantasyTeam, League $league, Matchday $matchday): bool|Response
    {
        if ($fantasyTeam->league_id !== $league->id || $matchday->season_id !== $league->season_id) {
            return Response::denyAsNotFound();
        }

        if ($fantasyTeam->user_id === $user->id) {
            return $this->view($user, $fantasyTeam);
        }

        return now()->greaterThanOrEqualTo($matchday->starts_at)
            && $league->users()->whereKey($user->id)->exists();
    }

    public function viewMatchdayScore(User $user, FantasyTeam $fantasyTeam, League $league, Matchday $matchday): bool|Response
    {
        if ($fantasyTeam->league_id !== $league->id || $matchday->season_id !== $league->season_id) {
            return Response::denyAsNotFound();
        }

        return now()->greaterThanOrEqualTo($matchday->starts_at)
            && $league->users()->whereKey($user->id)->exists();
    }
    public function manageFormation(User $user, FantasyTeam $fantasyTeam): bool
    {
        return $fantasyTeam->user_id === $user->id && $this->view($user, $fantasyTeam);
    }
}
