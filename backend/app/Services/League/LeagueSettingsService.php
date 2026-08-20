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
        if ($league->isFormulaOne()) {
            $league->settings()->firstOrCreate(
                ['key' => LeagueSetting::FORMULA_ONE_POSITION_POINTS],
                ['value' => LeagueSetting::positionPointsPayload(LeagueSetting::DEFAULT_FORMULA_ONE_POSITION_POINTS)],
            );
        }
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
        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_POINTS],
            ['value' => LeagueSetting::decimalPayload(LeagueSetting::DEFAULT_GOALKEEPER_CLEAN_SHEET_BONUS_POINTS)],
        );
        $league->settings()->firstOrCreate(
            ['key' => LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS],
            ['value' => LeagueSetting::defenseModifierThresholdsPayload(LeagueSetting::DEFAULT_DEFENSE_MODIFIER_THRESHOLDS)],
        );
        if ($league->isHeadToHead()) {
            foreach (
                [
                    LeagueSetting::FIRST_GOAL_THRESHOLD => LeagueSetting::DEFAULT_FIRST_GOAL_THRESHOLD,
                    LeagueSetting::GOAL_INTERVAL => LeagueSetting::DEFAULT_GOAL_INTERVAL,
                ] as $key => $value
            ) {
                $league->settings()->firstOrCreate(
                    ['key' => $key],
                    ['value' => LeagueSetting::decimalPayload($value)],
                );
            }
        }
        foreach (
            [
                LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => LeagueSetting::DEFAULT_ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
                LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => LeagueSetting::DEFAULT_REAL_CAPTAIN_BONUS_ENABLED,
                LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED => LeagueSetting::DEFAULT_GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED,
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
            $this->ensureSettingsApplyToLeagueType($lockedLeague, $settings);
            $this->ensureDefenseModifierThresholdsAreValid($settings);
            $this->ensureLifecycleAllows($lockedLeague, $settings);
            $this->ensureCombinedRosterRulesAreValid($lockedLeague, $settings);
            $this->ensureRosterCompatibility($lockedLeague, $settings);

            if (array_key_exists(LeagueSetting::FORMULA_ONE_POSITION_POINTS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::FORMULA_ONE_POSITION_POINTS],
                    ['value' => LeagueSetting::positionPointsPayload($settings[LeagueSetting::FORMULA_ONE_POSITION_POINTS])],
                );
            }

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

            if (array_key_exists(LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_POINTS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_POINTS],
                    ['value' => LeagueSetting::decimalPayload((float) $settings[LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_POINTS])],
                );
            }
            if (array_key_exists(LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS, $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $lockedLeague->id, 'key' => LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS],
                    ['value' => LeagueSetting::defenseModifierThresholdsPayload($settings[LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS])],
                );
            }

            foreach ([LeagueSetting::FIRST_GOAL_THRESHOLD, LeagueSetting::GOAL_INTERVAL] as $key) {
                if (array_key_exists($key, $settings)) {
                    LeagueSetting::query()->updateOrCreate(
                        ['league_id' => $lockedLeague->id, 'key' => $key],
                        ['value' => LeagueSetting::decimalPayload((float) $settings[$key])],
                    );
                }
            }

            foreach (
                [
                    LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
                    LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED,
                    LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED,
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
        $changedSettings = array_filter(
            $settings,
            fn(mixed $value, string $key): bool => $this->settingHasChanged($league, $key, $value),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changedSettings !== [] && in_array($league->statusKey(), [LeagueStatus::COMPLETED, LeagueStatus::ARCHIVED], true)) {
            throw new LeagueRulesLockedException('all');
        }

        if (
            array_key_exists(LeagueSetting::FORMULA_ONE_POSITION_POINTS, $changedSettings)
            && $league->hasInitializedChampionship()
        ) {
            throw new LeagueRulesLockedException('formula_one_position_points');
        }

        if (
            array_key_exists(LeagueSetting::INITIAL_BUDGET, $changedSettings)
            && FantasyTeam::query()->where('league_id', $league->id)->exists()
        ) {
            throw new UnsupportedInitialBudgetChangeException;
        }
    }

    /** @param array<string, mixed> $settings */
    private function ensureSettingsApplyToLeagueType(League $league, array $settings): void
    {
        if (! $league->isHeadToHead() && array_intersect(array_keys($settings), LeagueSetting::HEAD_TO_HEAD_KEYS) !== []) {
            throw new InvalidLeagueConfigurationException('Goal conversion settings only apply to head-to-head leagues.');
        }

        if (! $league->isFormulaOne() && array_intersect(array_keys($settings), LeagueSetting::FORMULA_ONE_KEYS) !== []) {
            throw new InvalidLeagueConfigurationException('Formula One position points only apply to formula one leagues.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function ensureDefenseModifierThresholdsAreValid(array $settings): void
    {
        if (! array_key_exists(LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS, $settings)) {
            return;
        }

        $rows = $settings[LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS];
        $ids = [];
        $thresholds = [];
        $previous = null;
        if (! is_array($rows) || $rows === []) {
            throw new InvalidLeagueConfigurationException('At least one defense modifier threshold is required.');
        }
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) !== 3 || array_diff(['id', 'threshold', 'bonus'], array_keys($row)) !== []) {
                throw new InvalidLeagueConfigurationException('Defense modifier thresholds have an invalid structure.');
            }
            $id = $row['id'];
            $threshold = $row['threshold'];
            $bonus = $row['bonus'];
            if (! is_string($id) || trim($id) === '' || isset($ids[$id]) || ! is_numeric($threshold) || ! is_numeric($bonus)) {
                throw new InvalidLeagueConfigurationException('Defense modifier thresholds must have unique ids and numeric values.');
            }
            $threshold = (float) $threshold;
            $bonus = (float) $bonus;
            if ($threshold < 0 || $bonus < 0 || abs($threshold * 4 - round($threshold * 4)) > 1e-8 || abs($bonus * 2 - round($bonus * 2)) > 1e-8 || isset($thresholds[(string) $threshold]) || ($previous !== null && $threshold <= $previous)) {
                throw new InvalidLeagueConfigurationException('Defense modifier thresholds must be unique, increasing, and use valid increments.');
            }
            $ids[$id] = true;
            $thresholds[(string) $threshold] = true;
            $previous = $threshold;
        }
    }

    private function settingHasChanged(League $league, string $key, mixed $incoming): bool
    {
        return match ($key) {
            LeagueSetting::INITIAL_BUDGET => (int) $incoming !== $league->initialFantasyBudget(),
            LeagueSetting::RELEASE_REFUND_PERCENTAGE => (int) $incoming !== $league->releaseRefundPercentage(),
            LeagueSetting::MAX_ROSTER_PLAYERS => (int) $incoming !== $league->maxRosterPlayers(),
            LeagueSetting::ROSTER_ROLE_LIMITS => $this->normalizeIntegerMap($incoming)
                !== $this->normalizeIntegerMap($league->rosterRoleLimits()),
            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES => $this->normalizeStringList($incoming)
                !== $league->allowedFormationModuleNames(),
            LeagueSetting::BENCH_SIZE => (int) $incoming !== $league->benchSize(),
            LeagueSetting::BENCH_ROLE_LIMITS => $this->normalizeIntegerMap($incoming)
                !== $this->normalizeIntegerMap($league->benchRoleLimits()),
            LeagueSetting::MAX_SUBSTITUTIONS => (int) $incoming !== $league->maxSubstitutions(),
            LeagueSetting::SUBSTITUTION_ORDER_MODE => (string) $incoming !== $league->substitutionOrderMode(),
            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION => (bool) $incoming
                !== $league->allowsFormationChangeOnSubstitution(),
            LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED => (bool) $incoming !== $league->realCaptainBonusEnabled(),
            LeagueSetting::REAL_CAPTAIN_BONUS_POINTS => (float) $incoming !== $league->realCaptainBonusPoints(),
            LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED => (bool) $incoming !== $league->goalkeeperCleanSheetBonusEnabled(),
            LeagueSetting::GOALKEEPER_CLEAN_SHEET_BONUS_POINTS => (float) $incoming !== $league->goalkeeperCleanSheetBonusPoints(),
            LeagueSetting::DEFENSE_MODIFIER_ENABLED => (bool) $incoming !== $league->defenseModifierEnabled(),
            LeagueSetting::FIRST_GOAL_THRESHOLD => (float) $incoming !== $league->firstGoalThreshold(),
            LeagueSetting::DEFENSE_MODIFIER_THRESHOLDS => $incoming !== $league->defenseModifierThresholds(),
            LeagueSetting::GOAL_INTERVAL => (float) $incoming !== $league->goalInterval(),
            LeagueSetting::FORMULA_ONE_POSITION_POINTS => $this->normalizePositionPoints($incoming)
                !== $this->normalizePositionPoints($league->formulaOnePositionPoints()),
            default => true,
        };
    }

    /** @return array<string, int> */
    private function normalizeIntegerMap(mixed $values): array
    {
        $normalized = collect(is_array($values) ? $values : [])->mapWithKeys(
            fn(mixed $value, string $key): array => [$key => (int) $value],
        )->all();
        ksort($normalized);

        return $normalized;
    }

    /** @return list<string> */
    private function normalizeStringList(mixed $values): array
    {
        $normalized = array_values(array_unique(array_map('strval', is_array($values) ? $values : [])));
        sort($normalized);

        return $normalized;
    }

    /** @return array<int, int> */
    private function normalizePositionPoints(mixed $points): array
    {
        $normalized = collect(is_array($points) ? $points : [])->mapWithKeys(
            fn(mixed $value, string|int $position): array => [(int) $position => (int) $value],
        )->all();
        ksort($normalized);

        return $normalized;
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
