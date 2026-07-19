<?php

namespace App\Services\FantasyTeam;

use App\Models\League;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Database\Eloquent\Builder;

class EligiblePlayerQueryService
{
    public function query(League $league, array $filters = []): Builder
    {
        return PlayerSeasonRegistration::query()
            ->with(['player', 'playerRole', 'seasonClub.realClub'])
            ->activeForSeason($league->season_id)
            ->whereNotExists(function ($query) use ($league): void {
                $query->selectRaw('1')
                    ->from('fantasy_team_players as active_assignments')
                    ->whereColumn('active_assignments.player_id', 'player_season_registrations.player_id')
                    ->where('active_assignments.league_id', $league->id)
                    ->whereNull('active_assignments.released_at');
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('players.display_name', 'like', "%{$search}%");
            })
            ->when($filters['role'] ?? null, function (Builder $query, string $role): void {
                $query->whereHas(
                    'playerRole',
                    fn (Builder $query) => $query->where('player_roles.key', $role)
                );
            })
            ->when($filters['club_id'] ?? null, function (Builder $query, int $clubId): void {
                $query->whereHas('seasonClub', function (Builder $query) use ($clubId): void {
                    $query->where(function (Builder $query) use ($clubId): void {
                        $query->where('season_clubs.id', $clubId)
                            ->orWhere('season_clubs.real_club_id', $clubId);
                    });
                });
            })
            ->join('players', 'players.id', '=', 'player_season_registrations.player_id')
            ->orderBy('players.display_name')
            ->orderBy('players.id')
            ->select('player_season_registrations.*');
    }
}