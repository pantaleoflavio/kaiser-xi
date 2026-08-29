<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matchday extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'number',
        'name',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function displayLabel(): string
    {
        return filled($this->name)
            ? "{$this->number} — {$this->name}"
            : (string) $this->number;
    }

    public function lineupDeadline(): mixed
    {
        return $this->starts_at;
    }

    public function temporalState(): string
    {
        return match (true) {
            now()->lt($this->starts_at) => 'upcoming',
            now()->gt($this->ends_at) => 'past',
            default => 'current',
        };
    }

    public function isFinished(): bool
    {
        return now()->gt($this->ends_at);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function realMatches(): HasMany
    {
        return $this->hasMany(RealMatch::class);
    }

    public function playerScores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
