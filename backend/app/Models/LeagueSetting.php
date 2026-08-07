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
    public const CAPTAIN_ENABLED = 'captain_enabled';
    public const VICE_CAPTAIN_ENABLED = 'vice_captain_enabled';
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
    public const DEFAULT_CAPTAIN_ENABLED = false;
    public const DEFAULT_VICE_CAPTAIN_ENABLED = false;
    public const PLAYER_ROLE_KEYS = ['goalkeeper', 'defender', 'midfielder', 'forward'];
    public const DEFAULT_ROSTER_ROLE_LIMITS = [
        'goalkeeper' => 3,
        'defender' => 8,
        'midfielder' => 8,
        'forward' => 6,
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
