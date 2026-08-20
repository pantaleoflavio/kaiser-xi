<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueSetting extends Model
{
    use HasFactory;

    public const INITIAL_BUDGET = 'initial_budget';
    public const RELEASE_REFUND_PERCENTAGE = 'release_refund_percentage';
    public const MAX_ROSTER_PLAYERS = 'max_roster_players';
    public const ROSTER_ROLE_LIMITS = 'roster_role_limits';
    public const ALLOWED_FORMATION_MODULE_NAMES = 'allowed_formation_module_names';
    public const BENCH_SIZE = 'bench_size';
    public const BENCH_ROLE_LIMITS = 'bench_role_limits';
    public const MAX_SUBSTITUTIONS = 'max_substitutions';
    public const SUBSTITUTION_ORDER_MODE = 'substitution_order_mode';
    public const ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION = 'allow_formation_change_on_substitution';
    public const REAL_CAPTAIN_BONUS_ENABLED = 'real_captain_bonus_enabled';
    public const REAL_CAPTAIN_BONUS_POINTS = 'real_captain_bonus_points';
    public const GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED = 'goalkeeper_clean_sheet_bonus_enabled';
    public const GOALKEEPER_CLEAN_SHEET_BONUS_POINTS = 'goalkeeper_clean_sheet_bonus_points';
    public const DEFENSE_MODIFIER_ENABLED = 'defense_modifier_enabled';
    public const DEFENSE_MODIFIER_THRESHOLDS = 'defense_modifier_thresholds';
    public const FIRST_GOAL_THRESHOLD = 'first_goal_threshold';
    public const GOAL_INTERVAL = 'goal_interval';
    public const FORMULA_ONE_POSITION_POINTS = 'formula_one_position_points';
    public const DEFAULT_INITIAL_BUDGET = 500;
    public const DEFAULT_RELEASE_REFUND_PERCENTAGE = 50;
    public const DEFAULT_MAX_ROSTER_PLAYERS = 25;
    public const DEFAULT_ALLOWED_FORMATION_MODULE_NAMES = [
        '3-4-3',
        '3-5-2',
        '4-3-3',
        '4-4-2',
        '4-5-1',
        '5-3-2',
        '5-4-1',
    ];
    public const DEFAULT_BENCH_SIZE = 7;
    public const DEFAULT_BENCH_ROLE_LIMITS = [
        'goalkeeper' => 1,
        'defender' => 3,
        'midfielder' => 3,
        'forward' => 3,
    ];
    public const DEFAULT_MAX_SUBSTITUTIONS = 3;
    public const SUBSTITUTION_ORDER_BENCH = 'bench_order';
    public const SUBSTITUTION_ORDER_ROLE_PRIORITY = 'role_priority';
    public const SUBSTITUTION_ORDER_MODES = [
        self::SUBSTITUTION_ORDER_BENCH,
        self::SUBSTITUTION_ORDER_ROLE_PRIORITY,
    ];
    public const DEFAULT_SUBSTITUTION_ORDER_MODE = self::SUBSTITUTION_ORDER_BENCH;
    public const DEFAULT_ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION = false;
    public const DEFAULT_REAL_CAPTAIN_BONUS_ENABLED = false;
    public const DEFAULT_REAL_CAPTAIN_BONUS_POINTS = 0.5;
    public const DEFAULT_GOALKEEPER_CLEAN_SHEET_BONUS_ENABLED = false;
    public const DEFAULT_GOALKEEPER_CLEAN_SHEET_BONUS_POINTS = 1.0;
    public const DEFAULT_DEFENSE_MODIFIER_ENABLED = false;
    public const DEFAULT_DEFENSE_MODIFIER_THRESHOLDS = [
        ['id' => 'default-600', 'threshold' => 6.0, 'bonus' => 1.0],
        ['id' => 'default-625', 'threshold' => 6.25, 'bonus' => 1.5],
        ['id' => 'default-650', 'threshold' => 6.5, 'bonus' => 2.0],
        ['id' => 'default-675', 'threshold' => 6.75, 'bonus' => 2.5],
        ['id' => 'default-700', 'threshold' => 7.0, 'bonus' => 3.0],
    ];
    public const PLAYER_ROLE_KEYS = ['goalkeeper', 'defender', 'midfielder', 'forward'];
    public const DEFAULT_FIRST_GOAL_THRESHOLD = 66.0;
    public const DEFAULT_GOAL_INTERVAL = 6.0;
    public const DEFAULT_FORMULA_ONE_POSITION_POINTS = [1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1];
    public const DEFAULT_ROSTER_ROLE_LIMITS = [
        'goalkeeper' => 3,
        'defender' => 8,
        'midfielder' => 8,
        'forward' => 6,
    ];

    public const HEAD_TO_HEAD_KEYS = [
        self::FIRST_GOAL_THRESHOLD,
        self::GOAL_INTERVAL,
    ];

    public const FORMULA_ONE_KEYS = [
        self::FORMULA_ONE_POSITION_POINTS,
    ];

    protected $fillable = [
        'league_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function integerValue(): int
    {
        return (int) ($this->value['amount'] ?? $this->value['percentage'] ?? $this->value['value'] ?? 0);
    }

    /** @return array<string, int> */
    public function roleLimitsValue(): array
    {
        return array_map('intval', $this->value['limits'] ?? []);
    }

    public function booleanValue(): bool
    {
        return (bool) ($this->value['enabled'] ?? false);
    }


    public function decimalValue(): float
    {
        return (float) ($this->value['value'] ?? 0);
    }

    /** @return list<string> */
    public function stringListValue(): array
    {
        $values = array_values(array_unique(array_map('strval', $this->value['values'] ?? [])));
        sort($values);

        return $values;
    }

    public function stringValue(): string
    {
        return (string) ($this->value['value'] ?? '');
    }

    /** @return array<int, int> */
    public function positionPointsValue(): array
    {
        return collect($this->value['positions'] ?? [])->mapWithKeys(
            fn(mixed $points, string|int $position): array => [(int) $position => (int) $points]
        )->all();
    }

    /** @return list<array{id: string, threshold: float, bonus: float}> */
    public function defenseModifierThresholdsValue(): array
    {
        return self::normalizeDefenseModifierThresholds($this->value['thresholds'] ?? []);
    }

    /** @param array<int, array{id: string, threshold: float|int, bonus: float|int}> $thresholds */
    public static function defenseModifierThresholdsPayload(array $thresholds): array
    {
        return ['thresholds' => self::normalizeDefenseModifierThresholds($thresholds)];
    }

    /** @return list<array{id: string, threshold: float, bonus: float}> */
    private static function normalizeDefenseModifierThresholds(array $thresholds): array
    {
        $normalized = array_map(fn(array $row): array => [
            'id' => (string) $row['id'],
            'threshold' => (float) $row['threshold'],
            'bonus' => (float) $row['bonus'],
        ], array_values($thresholds));
        usort($normalized, fn(array $a, array $b): int => $a['threshold'] <=> $b['threshold']);

        return $normalized;
    }

    /** @param array<int|string, int> $positions */
    public static function positionPointsPayload(array $positions): array
    {
        return ['positions' => $positions];
    }

    public static function integerPayload(string $key, int $value): array
    {
        return match ($key) {
            self::INITIAL_BUDGET => ['amount' => $value],
            self::RELEASE_REFUND_PERCENTAGE => ['percentage' => $value],
            self::MAX_ROSTER_PLAYERS => ['value' => $value],
            default => ['value' => $value],
        };
    }

    /** @param array<string, int> $limits */
    public static function roleLimitsPayload(array $limits): array
    {
        return ['limits' => $limits];
    }

    public static function booleanPayload(bool $enabled): array
    {
        return ['enabled' => $enabled];
    }

    public static function decimalPayload(float $value): array
    {
        return ['value' => $value];
    }

    /** @param list<string> $values */
    public static function stringListPayload(array $values): array
    {
        $values = array_values(array_unique(array_map('strval', $values)));
        sort($values);

        return ['values' => $values];
    }

    public static function stringPayload(string $value): array
    {
        return ['value' => $value];
    }
}
