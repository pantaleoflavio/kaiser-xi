<?php

namespace App\Services\League;

use App\Exceptions\IncompatibleRosterRoleLimitException;
use App\Exceptions\IncompatibleRosterSizeException;
use App\Exceptions\InvalidLeagueConfigurationException;
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
    public function initializeDefaults(League $league): void
    {
        foreach (
            [
                LeagueSetting::INITIAL_BUDGET => LeagueSetting::DEFAULT_INITIAL_BUDGET,
                LeagueSetting::RELEASE_REFUND_PERCENTAGE => LeagueSetting::DEFAULT_RELEASE_REFUND_PERCENTAGE,
                LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS,
                LeagueSetting::BENCH_SIZE => LeagueSetting::DEFAULT_BENCH_SIZE,
                LeagueSetting::MAX_SUBSTITUTIONS => LeagueSetting::DEFAULT_MAX_SUBSTITUTIONS,
            ] as $key => $value
        ) {
            $league->settings()->firstOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::integerPayload($key, $value)],
            );
        }

        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::ROSTER_ROLE_LIMITS],
            ['value' => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS)],
        );

        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::BENCH_ROLE_LIMITS],
            ['value' => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS)],
        );
        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES],
            ['value' => LeagueSetting::stringListPayload(LeagueSetting::DEFAULT_ALLOWED_FORMATION_MODULE_NAMES)],
        );
        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::SUBSTITUTION_ORDER_MODE],
            ['value' => LeagueSetting::stringPayload(LeagueSetting::DEFAULT_SUBSTITUTION_ORDER_MODE)],
        );
        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::REAL_CAPTAIN_BONUS_POINTS],
            ['value' => LeagueSetting::decimalPayload(LeagueSetting::DEFAULT_REAL_CAPTAIN_BONUS_POINTS)],
        );
        foreach (
            [
                LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => LeagueSetting::DEFAULT_ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
                LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => LeagueSetting::DEFAULT_REAL_CAPTAIN_BONUS_ENABLED,
                LeagueSetting::DEFENSE_MODIFIER_ENABLED =>
                LeagueSetting::DEFAULT_DEFENSE_MODIFIER_ENABLED,
            ] as $key => $enabled
        ) {
            $league->settings()->firstOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::booleanPayload($enabled)],
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

            foreach (
                [
                    LeagueSetting::INITIAL_BUDGET,
                    LeagueSetting::RELEASE_REFUND_PERCENTAGE,
                    LeagueSetting::MAX_ROSTER_PLAYERS,
                    LeagueSetting::BENCH_SIZE,
                    LeagueSetting::MAX_SUBSTITUTIONS,
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

            if (array_key_exists(LeagueSetting::BENCH_ROLE_LIMITS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::BENCH_ROLE_LIMITS],
                    ['value' => LeagueSetting::roleLimitsPayload($settings[LeagueSetting::BENCH_ROLE_LIMITS])],
                );
            }

            if (array_key_exists(LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES],
                    ['value' => LeagueSetting::stringListPayload($settings[LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES])],
                );
            }

            if (array_key_exists(LeagueSetting::SUBSTITUTION_ORDER_MODE, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::SUBSTITUTION_ORDER_MODE],
                    ['value' => LeagueSetting::stringPayload($settings[LeagueSetting::SUBSTITUTION_ORDER_MODE])],
                );
            }

            if (array_key_exists(LeagueSetting::REAL_CAPTAIN_BONUS_POINTS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::REAL_CAPTAIN_BONUS_POINTS],
                    ['value' => LeagueSetting::decimalPayload(
                        (float) $settings[LeagueSetting::REAL_CAPTAIN_BONUS_POINTS]
                    )],
                );
            }

            foreach (
                [
                    LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
                    LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED,
                    LeagueSetting::DEFENSE_MODIFIER_ENABLED,
                ] as $key
            ) {
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
        if (in_array($league->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true)) {
            throw new LeagueRulesLockedException('all');
        }

        if (
            array_key_exists(LeagueSetting::INITIAL_BUDGET, $settings)
            && FantasyTeam::query()->where('league_id', $league->id)->exists()
        ) {
            throw new UnsupportedInitialBudgetChangeException;
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
