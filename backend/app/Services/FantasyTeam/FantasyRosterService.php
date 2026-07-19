<?php

namespace App\Services\FantasyTeam;

use App\Exceptions\InsufficientFantasyBudgetException;
use App\Exceptions\InvalidLeaguePlayerRegistrationException;
use App\Exceptions\PlayerAlreadyAssignedInLeagueException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FantasyRosterService
{
    public function assign(League $league, FantasyTeam $team, Player $player, User $assignedBy, int $purchasePrice): FantasyTeamPlayer
    {
        return DB::transaction(function () use ($league, $team, $player, $assignedBy, $purchasePrice): FantasyTeamPlayer {
            $team = FantasyTeam::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            if (! $this->isPlayerEligible($league, $player)) {
                throw new InvalidLeaguePlayerRegistrationException;
            }

            if ($purchasePrice > (int) $team->remaining_budget) {
                throw new InsufficientFantasyBudgetException;
            }

            if (FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('player_id', $player->id)->exists()) {
                throw new PlayerAlreadyAssignedInLeagueException;
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

    public function release(League $league, FantasyTeam $team, Player $player): FantasyTeamPlayer
    {
        return DB::transaction(function () use ($league, $team, $player): FantasyTeamPlayer {
            $team = FantasyTeam::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
            $assignment = FantasyTeamPlayer::query()
                ->where('league_id', $league->id)
                ->where('fantasy_team_id', $team->id)
                ->where('player_id', $player->id)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $refund = $this->refundAmount((int) $assignment->purchase_price, $league->releaseRefundPercentage());
            $assignment->update(['released_at' => now()]);
            $team->increment('remaining_budget', $refund);

            return $assignment->refresh()->load('player.playerSeasonRegistrations.playerRole');
        });
    }

    public function refundAmount(int $purchasePrice, int $percentage): int
    {
        return (int) floor(($purchasePrice * $percentage / 100) + 0.5);
    }

    private function isPlayerEligible(League $league, Player $player): bool
    {
        return PlayerSeasonRegistration::query()
            ->where('player_id', $player->id)
            ->activeForSeason($league->season_id)
            ->exists();
    }
}