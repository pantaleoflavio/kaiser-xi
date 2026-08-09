<?php

namespace App\Models;

use App\Enums\PlayerScoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PlayerScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_season_registration_id',
        'matchday_id',
        'base_rating',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'own_goals',
        'penalties_scored',
        'penalties_missed',
        'penalties_saved',
        'goals_conceded',
        'clean_sheet',
        'final_score',
        'status',
    ];

    protected $casts = [
        'base_rating' => 'decimal:2',
        'clean_sheet' => 'boolean',
        'final_score' => 'decimal:2',
        'status' => PlayerScoreStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $score): void {
            $registrationSeasonId = PlayerSeasonRegistration::query()->with('seasonClub')->find($score->player_season_registration_id)?->seasonClub?->season_id;
            $matchdaySeasonId = Matchday::query()->find($score->matchday_id)?->season_id;
            if ($registrationSeasonId === null || $registrationSeasonId !== $matchdaySeasonId) {
                throw ValidationException::withMessages(['player_season_registration_id' => 'The player registration must belong to the matchday season.']);
            }
        });
    }

    public function isPlayable(): bool
    {
        return $this->status === PlayerScoreStatus::Confirmed
            && $this->final_score !== null;
    }

    public static function isPlayableFor(int $playerSeasonRegistrationId, int $matchdayId): bool
    {
        return self::query()
            ->where('player_season_registration_id', $playerSeasonRegistrationId)
            ->where('matchday_id', $matchdayId)
            ->first()
            ?->isPlayable() ?? false;
    }

    public function playerSeasonRegistration(): BelongsTo
    {
        return $this->belongsTo(PlayerSeasonRegistration::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function teamMatchdayScoreDetails(): HasMany
    {
        return $this->hasMany(TeamMatchdayScoreDetail::class);
    }
}
