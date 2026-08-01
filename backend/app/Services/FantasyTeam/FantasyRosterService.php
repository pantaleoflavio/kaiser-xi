<?php

namespace App\Services\FantasyTeam;

use App\Exceptions\FantasyRosterFullException;
use App\Exceptions\FantasyRosterRoleLimitReachedException;
use App\Exceptions\InsufficientFantasyBudgetException;
use App\Exceptions\InvalidLeagueConfigurationException;
use App\Exceptions\InvalidLeaguePlayerRegistrationException;
use App\Exceptions\PlayerAlreadyAssignedInLeagueException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FantasyRosterService
{
    public function assign(League $league, FantasyTeam $team, Player $player, User $assignedBy, int $purchasePrice): FantasyTeamPlayer
    {
        return DB::transaction(function () use ($league, $team, $player, $assignedBy, $purchasePrice): FantasyTeamPlayer {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $team = FantasyTeam::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            $registration = $this->activeRegistration($league, $player);
            if (! $registration) {
                throw new InvalidLeaguePlayerRegistrationException;
            }

            if ($purchasePrice > (int) $team->remaining_budget) {
                throw new InsufficientFantasyBudgetException;
            }

            if (FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('player_id', $player->id)->exists()) {
                throw new PlayerAlreadyAssignedInLeagueException;
            }

            $roleKey = $registration->playerRole?->key;
            $roleLimits = $league->rosterRoleLimits();
            $maxRosterPlayers = $league->maxRosterPlayers();
            if (
                $maxRosterPlayers < 1
                || count($roleLimits) !== count(LeagueSetting::PLAYER_ROLE_KEYS)
                || array_diff(LeagueSetting::PLAYER_ROLE_KEYS, array_keys($roleLimits)) !== []
                || array_sum($roleLimits) < $maxRosterPlayers
                || array_any($roleLimits, fn(mixed $limit): bool => ! is_int($limit) || $limit < 0)
            ) {
                throw new InvalidLeagueConfigurationException('The league roster limits are invalid.');
            }

            if (! is_string($roleKey) || ! in_array($roleKey, LeagueSetting::PLAYER_ROLE_KEYS, true)) {
                throw new InvalidLeagueConfigurationException('The player role is not configured for this league roster.');
            }

            $activeRoster = FantasyTeamPlayer::query()
                ->active()
                ->where('league_id', $league->id)
                ->where('fantasy_team_id', $team->id);

            if ((clone $activeRoster)->count() >= $maxRosterPlayers) {
                throw new FantasyRosterFullException;
            }

            $activeRoleCount = (clone $activeRoster)
                ->whereHas('player.playerSeasonRegistrations', function ($query) use ($league, $roleKey): void {
                    $query->activeForSeason($league->season_id)
                        ->whereHas('playerRole', fn($query) => $query->where('key', $roleKey));
                })
                ->count();

            if ($activeRoleCount >= $roleLimits[$roleKey]) {
                throw new FantasyRosterRoleLimitReachedException($roleKey);
            }

            $assignment = FantasyTeamPlayer::query()->create([
                'league_id' => $league->id,
                'fantasy_team_id' => $team->id,
                'player_id' => $player->id,
                'assigned_by_user_id' => $assignedBy->id,
                'purchase_price' => $purchasePrice,
                'assigned_at' => now(),
                'released_at' => null,
            ]);

            $team->decrement('remaining_budget', $purchasePrice);

            return $assignment->load('player.playerSeasonRegistrations.playerRole');
        });
    }

    public function release(League $league, FantasyTeam $team, Player $player, User $releasedBy): FantasyTeamPlayer
    {
        return DB::transaction(function () use ($league, $team, $player, $releasedBy): FantasyTeamPlayer {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $team = FantasyTeam::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $assignment = FantasyTeamPlayer::query()
                ->where('league_id', $league->id)
                ->where('fantasy_team_id', $team->id)
                ->where('player_id', $player->id)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $refund = $this->refundAmount((int) $assignment->purchase_price, $league->releaseRefundPercentage());
            $assignment->update(['released_at' => now(), 'released_by_user_id' => $releasedBy->id]);
            $team->increment('remaining_budget', $refund);

            return $assignment->refresh()->load('player.playerSeasonRegistrations.playerRole');
        });
    }

    public function refundAmount(int $purchasePrice, int $percentage): int
    {
        return (int) floor(($purchasePrice * $percentage / 100) + 0.5);
    }

    private function activeRegistration(League $league, Player $player): ?PlayerSeasonRegistration
    {
        return PlayerSeasonRegistration::query()
            ->where('player_id', $player->id)
            ->activeForSeason($league->season_id)
            ->with('playerRole')
            ->first();
    }
}