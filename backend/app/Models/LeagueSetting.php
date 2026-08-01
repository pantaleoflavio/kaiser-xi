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
    public const DEFAULT_INITIAL_BUDGET = 500;
    public const DEFAULT_RELEASE_REFUND_PERCENTAGE = 50;
    public const DEFAULT_MAX_ROSTER_PLAYERS = 25;
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
}
