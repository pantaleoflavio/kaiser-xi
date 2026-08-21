<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketPlayerQuery
{
    public function query(League $league, array $filters): Builder
    {
        $assignments = DB::table('fantasy_team_players as ftp')
            ->join('fantasy_teams as ft', 'ft.id', '=', 'ftp.fantasy_team_id')
            ->where('ftp.league_id', $league->id)->whereNull('ftp.released_at')
            ->select(['ftp.id as market_assignment_id', 'ftp.player_id', 'ft.id as market_team_id', 'ft.name as market_team_name', 'ft.user_id as market_team_user_id']);

        return PlayerSeasonRegistration::query()
            ->with(['player', 'playerRole', 'seasonClub.realClub'])
            ->activeForSeason($league->season_id)
            ->join('players', 'players.id', '=', 'player_season_registrations.player_id')
            ->leftJoinSub($assignments, 'market_assignment', 'market_assignment.player_id', '=', 'player_season_registrations.player_id')
            ->when($filters['search'] ?? null, fn(Builder $q, string $v) => $q->where('players.display_name', 'like', "%{$v}%"))
            ->when($filters['role'] ?? null, fn(Builder $q, string $v) => $q->whereHas('playerRole', fn(Builder $r) => $r->where('player_roles.key', $v)))
            ->when($filters['club_id'] ?? null, fn(Builder $q, int $v) => $q->whereHas('seasonClub', fn(Builder $c) => $c->where('season_clubs.real_club_id', $v)))
            ->when($filters['fantasy_team_id'] ?? null, fn(Builder $q, int $v) => $q->where('market_assignment.market_team_id', $v))
            ->when(($filters['assignment_state'] ?? null) === 'assigned', fn(Builder $q) => $q->whereNotNull('market_assignment.market_team_id'))
            ->when(($filters['assignment_state'] ?? null) === 'unassigned', fn(Builder $q) => $q->whereNull('market_assignment.market_team_id'))
            ->orderBy('players.display_name')->orderBy('players.id')
            ->select('player_season_registrations.*', 'market_assignment.market_team_id', 'market_assignment.market_team_name', 'market_assignment.market_team_user_id', 'market_assignment.market_assignment_id');
    }
}
