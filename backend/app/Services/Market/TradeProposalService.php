<?php

namespace App\Services\Market;

use App\Enums\TradeProposalStatus;
use App\Exceptions\TradeConflictException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\PlayerSeasonRegistration;
use App\Models\TradeProposal;
use App\Models\User;
use App\Services\League\MarketAvailability;
use Illuminate\Support\Facades\DB;

class TradeProposalService
{
    public function __construct(private MarketAvailability $availability) {}

    public function propose(League $league, User $user, array $data): TradeProposal
    {
        $this->open($league);
        $from = FantasyTeam::query()->where('league_id', $league->id)->where('user_id', $user->id)->first();
        $to = FantasyTeam::query()->whereKey($data['receiving_fantasy_team_id'])->where('league_id', $league->id)->first();
        if (! $from || ! $to) $this->conflict('invalid_teams', 'Both fantasy teams must be current teams in this league.');
        if ($from->is($to)) $this->conflict('same_team', 'A team cannot trade with itself.');
        $offered = $this->activeAssignment($league, $from, (int) $data['offered_fantasy_team_player_id']);
        $requested = $this->activeAssignment($league, $to, (int) $data['requested_fantasy_team_player_id']);
        $cash = (float) ($data['cash_amount'] ?? 0);
        $payer = $data['cash_from_fantasy_team_id'] ?? null;
        $this->cash($league, $from, $to, $cash, $payer);

        return TradeProposal::query()->create([
            'league_id' => $league->id,
            'from_team_id' => $from->id,
            'to_team_id' => $to->id,
            'offered_fantasy_team_player_id' => $offered->id,
            'requested_fantasy_team_player_id' => $requested->id,
            'cash_paid_by_team_id' => $cash > 0 ? $payer : null,
            'cash_amount' => $cash,
            'status' => TradeProposalStatus::Pending,
        ]);
    }

    public function accept(League $league, TradeProposal $trade, User $user): TradeProposal
    {
        return DB::transaction(function () use ($league, $trade, $user): TradeProposal {
            $trade = TradeProposal::query()->whereKey($trade->id)->lockForUpdate()->firstOrFail();
            $this->pending($trade);
            $this->open($league);
            $teams = FantasyTeam::query()->whereIn('id', [$trade->from_team_id, $trade->to_team_id])->where('league_id', $league->id)->lockForUpdate()->get()->keyBy('id');
            $from = $teams->get($trade->from_team_id);
            $to = $teams->get($trade->to_team_id);
            if (! $from || ! $to) $this->conflict('invalid_teams', 'A fantasy team is no longer in this league.');
            if ($to->user_id !== $user->id) $this->forbidden();
            $assignments = FantasyTeamPlayer::query()->whereIn('id', [$trade->offered_fantasy_team_player_id, $trade->requested_fantasy_team_player_id])->lockForUpdate()->get()->keyBy('id');
            $offered = $assignments->get($trade->offered_fantasy_team_player_id);
            $requested = $assignments->get($trade->requested_fantasy_team_player_id);
            $this->stillOwned($offered, $league, $from);
            $this->stillOwned($requested, $league, $to);
            $this->cash($league, $from, $to, (float) $trade->cash_amount, $trade->cash_paid_by_team_id);
            $this->rosterAfterSwap($league, $from, $offered, $requested);
            $this->rosterAfterSwap($league, $to, $requested, $offered);
            $payer = $trade->cash_paid_by_team_id ? $teams->get($trade->cash_paid_by_team_id) : null;
            if ($payer && (float) $payer->remaining_budget < (float) $trade->cash_amount) $this->conflict('insufficient_budget', 'The paying team has insufficient remaining budget.');
            $at = now();
            $offered->update(['released_at' => $at, 'released_by_user_id' => $user->id]);
            $requested->update(['released_at' => $at, 'released_by_user_id' => $user->id]);
            FantasyTeamPlayer::query()->create(['league_id' => $league->id, 'fantasy_team_id' => $to->id, 'player_id' => $offered->player_id, 'assigned_by_user_id' => $user->id, 'purchase_price' => $offered->purchase_price, 'assigned_at' => $at]);
            FantasyTeamPlayer::query()->create(['league_id' => $league->id, 'fantasy_team_id' => $from->id, 'player_id' => $requested->player_id, 'assigned_by_user_id' => $user->id, 'purchase_price' => $requested->purchase_price, 'assigned_at' => $at]);
            if ($payer) {
                $receiver = $payer->is($from) ? $to : $from;
                $payer->decrement('remaining_budget', $trade->cash_amount);
                $receiver->increment('remaining_budget', $trade->cash_amount);
            }
            $trade->update(['status' => TradeProposalStatus::Accepted, 'accepted_at' => $at]);
            return $trade->refresh();
        });
    }

    public function reject(TradeProposal $trade, User $user): TradeProposal
    {
        return $this->terminal($trade, $user, false);
    }
    public function cancel(TradeProposal $trade, User $user): TradeProposal
    {
        return $this->terminal($trade, $user, true);
    }

    private function terminal(TradeProposal $trade, User $user, bool $cancel): TradeProposal
    {
        return DB::transaction(function () use ($trade, $user, $cancel): TradeProposal {
            $trade = TradeProposal::query()->whereKey($trade->id)->lockForUpdate()->firstOrFail();
            $this->pending($trade);
            $teamId = $cancel ? $trade->from_team_id : $trade->to_team_id;
            if (! FantasyTeam::query()->whereKey($teamId)->where('user_id', $user->id)->exists()) $this->forbidden();
            $field = $cancel ? 'cancelled_at' : 'rejected_at';
            $status = $cancel ? TradeProposalStatus::Cancelled : TradeProposalStatus::Rejected;
            $trade->update(['status' => $status, $field => now()]);
            return $trade->refresh();
        });
    }

    private function rosterAfterSwap(League $league, FantasyTeam $team, FantasyTeamPlayer $out, FantasyTeamPlayer $in): void
    {
        $roles = $league->rosterRoleLimits();
        if ($league->maxRosterPlayers() < 1 || count($roles) !== count(LeagueSetting::PLAYER_ROLE_KEYS)) $this->conflict('invalid_roster', 'League roster configuration is invalid.');
        $incomingRole = PlayerSeasonRegistration::query()->activeForSeason($league->season_id)->where('player_id', $in->player_id)->whereHas('playerRole')->with('playerRole')->first()?->playerRole?->key;
        if (! is_string($incomingRole) || ! isset($roles[$incomingRole])) $this->conflict('invalid_player_registration', 'A player no longer has a valid league-season registration.');
        $count = FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('fantasy_team_id', $team->id)->whereKeyNot($out->id)->whereHas('player.playerSeasonRegistrations', fn($q) => $q->activeForSeason($league->season_id)->whereHas('playerRole', fn($q) => $q->where('key', $incomingRole)))->count();
        if ($count + 1 > $roles[$incomingRole]) $this->conflict('invalid_roster', 'The resulting roster exceeds a role limit.');
    }

    private function activeAssignment(League $league, FantasyTeam $team, int $id): FantasyTeamPlayer
    {
        $a = FantasyTeamPlayer::query()->active()->whereKey($id)->where('league_id', $league->id)->where('fantasy_team_id', $team->id)->first();
        if (! $a) $this->conflict('player_not_owned', 'The player is not actively assigned to the expected team.');
        $this->registration($league, $a);
        return $a;
    }
    private function stillOwned(?FantasyTeamPlayer $a, League $l, FantasyTeam $t): void
    {
        if (! $a || $a->released_at || $a->league_id !== $l->id || $a->fantasy_team_id !== $t->id) $this->conflict('player_not_owned', 'The player is no longer actively assigned to the expected team.');
        $this->registration($l, $a);
    }
    private function registration(League $l, FantasyTeamPlayer $a): void
    {
        if (! PlayerSeasonRegistration::query()->activeForSeason($l->season_id)->where('player_id', $a->player_id)->exists()) $this->conflict('invalid_player_registration', 'The player has no valid league-season registration.');
    }
    private function cash(League $l, FantasyTeam $a, FantasyTeam $b, float $amount, mixed $payer): void
    {
        if ($amount < 0 || (! $l->tradeCashAdjustmentEnabled() && ($amount > 0 || $payer !== null))) $this->conflict('cash_adjustment_disabled', 'Cash adjustment is disabled.');
        if ($amount > 0 && ! in_array((int) $payer, [$a->id, $b->id], true)) $this->conflict('invalid_cash_payer', 'Cash payer must be one of the trading teams.');
        if ($amount === 0.0 && $payer !== null) $this->conflict('invalid_cash_payer', 'Zero cash must not specify a payer.');
    }
    private function pending(TradeProposal $t): void
    {
        if ($t->status !== TradeProposalStatus::Pending) $this->conflict('trade_not_pending', 'The trade is no longer pending.');
    }
    private function open(League $l): void
    {
        if (! $this->availability->isOpen($l)) $this->conflict('market_closed', 'The League Market is closed.');
    }
    private function forbidden(): never
    {
        abort(403, 'You cannot perform this trade action.');
    }
    private function conflict(string $code, string $message): never
    {
        throw new TradeConflictException($code, $message);
    }
}
