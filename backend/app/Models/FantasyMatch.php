<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FantasyMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'matchday_id',
        'home_fantasy_team_id',
        'away_fantasy_team_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (FantasyMatch $match): void {
            if ($match->home_fantasy_team_id === $match->away_fantasy_team_id) {
                throw new \DomainException('A fantasy team cannot play itself.');
            }
        });
    }


    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function homeFantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'home_fantasy_team_id');
    }

    public function awayFantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'away_fantasy_team_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(FantasyMatchResult::class);
    }
}
