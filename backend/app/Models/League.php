<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'league_type_id',
        'league_status_id',
        'commissioner_user_id',
        'name',
        'slug',
        'description',
        'max_participants',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeagueType::class, 'league_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeagueStatus::class, 'league_status_id');
    }

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LeagueMembership::class)
            ->withPivot('league_role_id', 'joined_at')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(LeagueSetting::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(LeagueInvitation::class);
    }

    public function settingValue(string $key, int $default): int
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting instanceof LeagueSetting ? $setting->integerValue() : $default;
    }

    public function initialFantasyBudget(): int
    {
        return $this->settingValue(LeagueSetting::INITIAL_BUDGET, LeagueSetting::DEFAULT_INITIAL_BUDGET);
    }

    public function releaseRefundPercentage(): int
    {
        return $this->settingValue(LeagueSetting::RELEASE_REFUND_PERCENTAGE, LeagueSetting::DEFAULT_RELEASE_REFUND_PERCENTAGE);
    }

    public function maxRosterPlayers(): int
    {
        return $this->settingValue(LeagueSetting::MAX_ROSTER_PLAYERS, LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS);
    }

    /** @return array<string, int> */
    public function rosterRoleLimits(): array
    {
        $setting = $this->settings()
            ->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)
            ->first();

        $storedLimits = $setting instanceof LeagueSetting
            ? $setting->roleLimitsValue()
            : [];

        $orderedLimits = [];

        foreach (LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS as $role => $defaultLimit) {
            $orderedLimits[$role] = (int) ($storedLimits[$role] ?? $defaultLimit);
        }

        return $orderedLimits;
    }
}
