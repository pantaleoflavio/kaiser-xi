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
    public const DEFAULT_INITIAL_BUDGET = 500;
    public const DEFAULT_RELEASE_REFUND_PERCENTAGE = 50;

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

    public static function integerPayload(string $key, int $value): array
    {
        return match ($key) {
            self::INITIAL_BUDGET => ['amount' => $value],
            self::RELEASE_REFUND_PERCENTAGE => ['percentage' => $value],
            default => ['value' => $value],
        };
    }
}
