<?php

namespace App\Models;

use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerScore;
use App\Models\SeasonClub;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlayerSeasonRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'season_club_id',
        'player_role_id',
        'external_provider',
        'external_id',
        'shirt_number',
        'quotation',
        'is_active',
        'registered_at',
        'released_at',
    ];

    protected $casts = [
        'quotation' => 'decimal:2',
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $registration): void {
            $registration->external_provider = filled($registration->external_provider)
                ? mb_strtolower(trim((string) $registration->external_provider))
                : null;

            Validator::make($registration->attributesToArray(), [
                'external_provider' => ['nullable', 'string', 'max:255', 'required_with:external_id'],
                'external_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    'required_with:external_provider',
                    Rule::unique('player_season_registrations', 'external_id')
                        ->where(fn($query) => $query->where('external_provider', $registration->external_provider))
                        ->ignore($registration->getKey()),
                ],
            ], [
                'external_provider.required_with' => __('admin.validation.external_identity_pair'),
                'external_id.required_with' => __('admin.validation.external_identity_pair'),
                'external_id.unique' => __('admin.validation.external_identity_unique'),
            ])->validate();
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function seasonClub(): BelongsTo
    {
        return $this->belongsTo(SeasonClub::class);
    }

    public function playerRole(): BelongsTo
    {
        return $this->belongsTo(PlayerRole::class);
    }

    public function playerScores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function scopeActiveForSeason(Builder $query, int $seasonId): Builder
    {
        return $query->activeForSeasonIds([$seasonId]);
    }

    /** @param array<int, int> $seasonIds */
    public function scopeActiveForSeasonIds(Builder $query, array $seasonIds): Builder
    {
        return $query
            ->where('player_season_registrations.is_active', true)
            ->whereNull('player_season_registrations.released_at')
            ->whereHas('seasonClub', fn(Builder $query) => $query->whereIn('season_clubs.season_id', $seasonIds));
    }
}
