<?php

namespace App\Services\League;

use App\Exceptions\IncompatibleRosterRoleLimitException;
use App\Exceptions\IncompatibleRosterSizeException;
use App\Exceptions\InvalidLeagueConfigurationException;
use App\Exceptions\LeagueMutabilityFlagsLockedException;
use App\Exceptions\LeagueRulesLockedException;
use App\Exceptions\UnsupportedInitialBudgetChangeException;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\LeagueStatus;
use Illuminate\Support\Facades\DB;

class LeagueSettingsService
{
    private const MUTABILITY_FIELDS = [
        LeagueSetting::BUDGET_RULES_MUTABLE,
        LeagueSetting::ROSTER_SIZE_MUTABLE,
        LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
    ];

    public function initializeDefaults(League $league): void
    {
        foreach ([
            LeagueSetting::INITIAL_BUDGET => LeagueSetting::DEFAULT_INITIAL_BUDGET,
            LeagueSetting::RELEASE_REFUND_PERCENTAGE => LeagueSetting::DEFAULT_RELEASE_REFUND_PERCENTAGE,
                LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS,
            ] as $key => $value
        ) {
            $league->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::integerPayload($key, $value)],
            );
        }

        $league->settings()->updateOrCreate(
            ['key' => LeagueSetting::ROSTER_ROLE_LIMITS],
            ['value' => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS)],
        );

        foreach (
            [
                LeagueSetting::BUDGET_RULES_MUTABLE => LeagueSetting::DEFAULT_BUDGET_RULES_MUTABLE,
                LeagueSetting::ROSTER_SIZE_MUTABLE => LeagueSetting::DEFAULT_ROSTER_SIZE_MUTABLE,
                LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE => LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS_MUTABLE,
            ] as $key => $value
        ) {
            $league->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::booleanPayload($value)],
            );
        }
    }
    /** @param array<string, mixed> $settings */
    public function update(League $league, array $settings): League
    {
        DB::transaction(function () use ($league, $settings): void {
            $lockedLeague = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $this->ensureLifecycleAllows($lockedLeague, $settings);
            $this->ensureCombinedRosterRulesAreValid($lockedLeague, $settings);
            $this->ensureRosterCompatibility($lockedLeague, $settings);

            foreach ([
                    LeagueSetting::INITIAL_BUDGET,
                    LeagueSetting::RELEASE_REFUND_PERCENTAGE,
                    LeagueSetting::MAX_ROSTER_PLAYERS,
                ] as $key
            ) {
                if (! array_key_exists($key, $settings)) {
                    continue;
                }

                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => $key],
                    ['value' => LeagueSetting::integerPayload($key, (int) $settings[$key])],
                );
            }
            if (array_key_exists(LeagueSetting::ROSTER_ROLE_LIMITS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::ROSTER_ROLE_LIMITS],
                    ['value' => LeagueSetting::roleLimitsPayload($settings[LeagueSetting::ROSTER_ROLE_LIMITS])],
                );
            }

            foreach (self::MUTABILITY_FIELDS as $key) {
                if (array_key_exists($key, $settings)) {
                    LeagueSetting::query()->updateOrCreate(
                        ['league_id' => $lockedLeague->id, 'key' => $key],
                        ['value' => LeagueSetting::booleanPayload((bool) $settings[$key])],
                    );
                }
            }
        });

        return $league->refresh();
    }
    /** @param array<string, mixed> $settings */
    private function ensureLifecycleAllows(League $league, array $settings): void
    {
        if ($league->isPreActivation()) {
            return;
        }

        if (array_intersect(self::MUTABILITY_FIELDS, array_keys($settings)) !== []) {
            throw new LeagueMutabilityFlagsLockedException;
        }

        if (in_array($league->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true)) {
            throw new LeagueRulesLockedException('all');
        }

        if ($league->statusKey() !== LeagueStatus::ACTIVE) {
            throw new LeagueRulesLockedException('all');
        }

        if ((array_key_exists(LeagueSetting::INITIAL_BUDGET, $settings)
                || array_key_exists(LeagueSetting::RELEASE_REFUND_PERCENTAGE, $settings))
            && ! $league->budgetRulesMutable()
        ) {
            throw new LeagueRulesLockedException('budget');
        }

        if (
            array_key_exists(LeagueSetting::INITIAL_BUDGET, $settings)
            && FantasyTeam::query()->where('league_id', $league->id)->exists()
        ) {
            throw new UnsupportedInitialBudgetChangeException;
        }

        if (array_key_exists(LeagueSetting::MAX_ROSTER_PLAYERS, $settings) && ! $league->rosterSizeMutable()) {
            throw new LeagueRulesLockedException('roster_size');
        }

        if (array_key_exists(LeagueSetting::ROSTER_ROLE_LIMITS, $settings) && ! $league->rosterRoleLimitsMutable()) {
            throw new LeagueRulesLockedException('roster_role_limits');
        }
    }

    /** @param array<string, mixed> $settings */
    private function ensureCombinedRosterRulesAreValid(League $league, array $settings): void
    {
        $maximum = (int) ($settings[LeagueSetting::MAX_ROSTER_PLAYERS] ?? $league->maxRosterPlayers());
        $limits = $settings[LeagueSetting::ROSTER_ROLE_LIMITS] ?? $league->rosterRoleLimits();

        if (array_sum($limits) < $maximum) {
            throw new InvalidLeagueConfigurationException(
                'The sum of roster role limits must be greater than or equal to the maximum roster players.'
            );
        }
    }

    /** @param array<string, mixed> $settings */
    private function ensureRosterCompatibility(League $league, array $settings): void
    {
        if (array_key_exists(LeagueSetting::MAX_ROSTER_PLAYERS, $settings)) {
            $largest = FantasyTeamPlayer::query()->active()
                ->where('league_id', $league->id)
                ->selectRaw('fantasy_team_id, count(*) as aggregate')
                ->groupBy('fantasy_team_id')
                ->pluck('aggregate')->map(fn($count): int => (int) $count)->max() ?? 0;

            if ((int) $settings[LeagueSetting::MAX_ROSTER_PLAYERS] < $largest) {
                throw new IncompatibleRosterSizeException($largest);
            }
        }

        if (! array_key_exists(LeagueSetting::ROSTER_ROLE_LIMITS, $settings)) {
            return;
        }

        foreach ($settings[LeagueSetting::ROSTER_ROLE_LIMITS] as $role => $limit) {
            $largest = FantasyTeamPlayer::query()->active()
                ->where('fantasy_team_players.league_id', $league->id)
                ->whereHas('player.playerSeasonRegistrations', function ($query) use ($league, $role): void {
                    $query->activeForSeason($league->season_id)
                        ->whereHas('playerRole', fn($query) => $query->where('key', $role));
                })
                ->selectRaw('fantasy_team_id, count(*) as aggregate')
                ->groupBy('fantasy_team_id')
                ->pluck('aggregate')->map(fn($count): int => (int) $count)->max() ?? 0;

            if ((int) $limit < $largest) {
                throw new IncompatibleRosterRoleLimitException($role, $largest);
            }
        }
    }
}