<?php

namespace App\Services\League;

use App\Exceptions\IncompleteLeagueConfigurationException;
use App\Exceptions\LeagueActivationStateException;
use App\Exceptions\LeagueAlreadyActiveException;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use Illuminate\Support\Facades\DB;

class LeagueActivationService
{
    public function activate(League $league): League
    {
        DB::transaction(function () use ($league): void {
            $lockedLeague = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $this->validateReadiness($lockedLeague);

            $activeStatusId = LeagueStatus::query()->where('key', LeagueStatus::ACTIVE)->value('id');
            if (! is_int($activeStatusId)) {
                throw new IncompleteLeagueConfigurationException('The active league status is not configured.');
            }

            $lockedLeague->update(['league_status_id' => $activeStatusId]);
        });

        return $league->refresh();
    }

    public function validateReadiness(League $league): void
    {
        $status = $league->statusKey();

        if ($status === LeagueStatus::ACTIVE) {
            throw new LeagueAlreadyActiveException;
        }

        if (! in_array($status, [LeagueStatus::DRAFT, LeagueStatus::SETUP], true)) {
            throw new LeagueActivationStateException;
        }

        if (! $league->season()->exists() || ! $league->type()->exists()) {
            throw new IncompleteLeagueConfigurationException(
                'The league must have a valid season and type.'
            );
        }

        $requiredKeys = [
            LeagueSetting::INITIAL_BUDGET,
            LeagueSetting::RELEASE_REFUND_PERCENTAGE,
            LeagueSetting::MAX_ROSTER_PLAYERS,
            LeagueSetting::ROSTER_ROLE_LIMITS,
            LeagueSetting::BUDGET_RULES_MUTABLE,
            LeagueSetting::ROSTER_SIZE_MUTABLE,
            LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
        ];

        $persistedKeys = $league->settings()
            ->whereIn('key', $requiredKeys)
            ->pluck('key')
            ->all();

        if (array_diff($requiredKeys, $persistedKeys) !== []) {
            throw new IncompleteLeagueConfigurationException(
                'All required league settings must be persisted before activation.'
            );
        }

        $storedLimits = $league->settings()
            ->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)
            ->firstOrFail()
            ->roleLimitsValue();

        $limits = $league->rosterRoleLimits();

        $storedRoleKeys = array_keys($storedLimits);
        $expectedRoleKeys = LeagueSetting::PLAYER_ROLE_KEYS;

        $hasInvalidRoleKeys =
            array_diff($expectedRoleKeys, $storedRoleKeys) !== []
            || array_diff($storedRoleKeys, $expectedRoleKeys) !== [];

        $hasInvalidRoleLimits = array_any(
            $limits,
            fn (mixed $limit): bool => ! is_int($limit) || $limit < 0
        );

        if (
            $league->initialFantasyBudget() < 0
            || $league->releaseRefundPercentage() < 0
            || $league->releaseRefundPercentage() > 100
            || $league->maxRosterPlayers() < 1
            || $hasInvalidRoleKeys
            || $hasInvalidRoleLimits
            || array_sum($limits) < $league->maxRosterPlayers()
        ) {
            throw new IncompleteLeagueConfigurationException(
                'The persisted budget or roster rules are invalid.'
            );
        }

        $commissionerRoleId = LeagueRole::query()
            ->where('key', 'commissioner')
            ->value('id');

        $hasCommissionerMembership = $league->memberships()
            ->where('user_id', $league->commissioner_user_id)
            ->where('league_role_id', $commissionerRoleId)
            ->exists();

        if (! $league->commissioner()->exists() || ! $hasCommissionerMembership) {
            throw new IncompleteLeagueConfigurationException(
                'The creator commissioner membership is missing.'
            );
        }
    }
}