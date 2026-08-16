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
        'h2h_start_matchday_id',
        'h2h_schedule_generated_at',
        'classic_start_matchday_id',
        'classic_started_at',
    ];

    protected $casts = [
        'h2h_schedule_generated_at' => 'datetime',
        'classic_started_at' => 'datetime',
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

    public function fantasyMatches(): HasMany
    {
        return $this->hasMany(FantasyMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function h2hStartMatchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class, 'h2h_start_matchday_id');
    }

    public function hasInitializedHeadToHeadSchedule(): bool
    {
        return $this->h2h_schedule_generated_at !== null;
    }

    public function classicStartMatchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class, 'classic_start_matchday_id');
    }

    public function classicParticipants(): BelongsToMany
    {
        return $this->belongsToMany(FantasyTeam::class, 'classic_league_participants')->withTimestamps();
    }

    public function isClassic(): bool
    {
        return $this->type()->where('key', 'classic')->exists();
    }

    public function hasInitializedClassicChampionship(): bool
    {
        return $this->classic_started_at !== null;
    }

    public function hasStartedFantasyCompetition(): bool
    {
        return $this->hasInitializedHeadToHeadSchedule() || $this->hasInitializedClassicChampionship();
    }

    public function isHeadToHead(): bool
    {
        return $this->type()->where('key', 'head_to_head')->exists();
    }

    public function allowsFormationFor(Matchday $matchday): bool
    {
        if ($matchday->season_id !== $this->season_id) {
            return false;
        }

        if ($this->isClassic()) {
            return $this->hasInitializedClassicChampionship()
                && $this->classic_start_matchday_id !== null
                && $matchday->starts_at->greaterThanOrEqualTo($this->classicStartMatchday()->value('starts_at'));
        }

        if (! $this->isHeadToHead()) {
            return true;
        }

        return $this->hasInitializedHeadToHeadSchedule()
            && $this->h2h_start_matchday_id !== null
            && $matchday->starts_at->greaterThanOrEqualTo($this->h2hStartMatchday()->value('starts_at'))
            && $this->fantasyMatches()->where('matchday_id', $matchday->id)->exists();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(LeagueSetting::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(LeagueInvitation::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    public function settingValue(string $key, int $default): int
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting instanceof LeagueSetting ? $setting->integerValue() : $default;
    }

    public function booleanSettingValue(string $key, bool $default): bool
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting instanceof LeagueSetting ? $setting->booleanValue() : $default;
    }

    public function decimalSettingValue(string $key, float $default): float
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting instanceof LeagueSetting ? $setting->decimalValue() : $default;
    }

    public function stringSettingValue(string $key, string $default): string
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting instanceof LeagueSetting ? $setting->stringValue() : $default;
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

    public function statusKey(): ?string
    {
        return $this->status()->value('key');
    }

    public function isPreActivation(): bool
    {
        return in_array($this->statusKey(), [LeagueStatus::DRAFT, LeagueStatus::SETUP], true);
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

    /** @return list<string> */
    public function allowedFormationModuleNames(): array
    {
        $setting = $this->settings()->where('key', LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES)->first();

        return $setting instanceof LeagueSetting
            ? $setting->stringListValue()
            : LeagueSetting::DEFAULT_ALLOWED_FORMATION_MODULE_NAMES;
    }

    public function benchSize(): int
    {
        return $this->settingValue(LeagueSetting::BENCH_SIZE, LeagueSetting::DEFAULT_BENCH_SIZE);
    }

    /** @return array<string, int> */
    public function benchRoleLimits(): array
    {
        $setting = $this->settings()->where('key', LeagueSetting::BENCH_ROLE_LIMITS)->first();
        $stored = $setting instanceof LeagueSetting ? $setting->roleLimitsValue() : [];

        return collect(LeagueSetting::DEFAULT_BENCH_ROLE_LIMITS)
            ->mapWithKeys(fn(int $default, string $role): array => [$role => (int) ($stored[$role] ?? $default)])
            ->all();
    }

    public function maxSubstitutions(): int
    {
        return $this->settingValue(LeagueSetting::MAX_SUBSTITUTIONS, LeagueSetting::DEFAULT_MAX_SUBSTITUTIONS);
    }

    public function substitutionOrderMode(): string
    {
        return $this->stringSettingValue(LeagueSetting::SUBSTITUTION_ORDER_MODE, LeagueSetting::DEFAULT_SUBSTITUTION_ORDER_MODE);
    }

    public function allowsFormationChangeOnSubstitution(): bool
    {
        return $this->booleanSettingValue(LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION, false);
    }

    public function realCaptainBonusEnabled(): bool
    {
        return $this->booleanSettingValue(LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED, false);
    }

    public function realCaptainBonusPoints(): float
    {
        return $this->decimalSettingValue(
            LeagueSetting::REAL_CAPTAIN_BONUS_POINTS,
            LeagueSetting::DEFAULT_REAL_CAPTAIN_BONUS_POINTS,
        );
    }

    public function defenseModifierEnabled(): bool
    {
        return $this->booleanSettingValue(
            LeagueSetting::DEFENSE_MODIFIER_ENABLED,
            LeagueSetting::DEFAULT_DEFENSE_MODIFIER_ENABLED,
        );
    }

    public function firstGoalThreshold(): float
    {
        return $this->decimalSettingValue(
            LeagueSetting::FIRST_GOAL_THRESHOLD,
            LeagueSetting::DEFAULT_FIRST_GOAL_THRESHOLD,
        );
    }

    public function goalInterval(): float
    {
        return $this->decimalSettingValue(LeagueSetting::GOAL_INTERVAL, LeagueSetting::DEFAULT_GOAL_INTERVAL);
    }
}
