<?php

namespace App\Models;

use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\RealMatch;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SeasonClub extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'real_club_id',
        'display_name',
        'external_provider',
        'external_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $seasonClub): void {
            $seasonClub->external_provider = filled($seasonClub->external_provider)
                ? mb_strtolower(trim((string) $seasonClub->external_provider))
                : null;

            Validator::make($seasonClub->attributesToArray(), [
                'external_provider' => ['nullable', 'string', 'max:255', 'required_with:external_id'],
                'external_id' => [
                    'nullable',
                    'string',
                    'max:255',
                    'required_with:external_provider',
                    Rule::unique('season_clubs', 'external_id')
                        ->where(fn($query) => $query->where('external_provider', $seasonClub->external_provider))
                        ->ignore($seasonClub->getKey()),
                ],
            ], [
                'external_provider.required_with' => __('admin.validation.external_identity_pair'),
                'external_id.required_with' => __('admin.validation.external_identity_pair'),
                'external_id.unique' => __('admin.validation.external_identity_unique'),
            ])->validate();
        });
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function playerSeasonRegistrations(): HasMany
    {
        return $this->hasMany(PlayerSeasonRegistration::class);
    }

    public function realClub(): BelongsTo
    {
        return $this->belongsTo(RealClub::class);
    }

    public function homeRealMatches(): HasMany
    {
        return $this->hasMany(RealMatch::class, 'home_season_club_id');
    }

    public function awayRealMatches(): HasMany
    {
        return $this->hasMany(RealMatch::class, 'away_season_club_id');
    }
}
