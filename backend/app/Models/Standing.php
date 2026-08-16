<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'fantasy_team_id',
        'points_total',
        'fantasy_points_total',
        'average_points',
        'best_matchday_score',
        'played',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
        'position',
        'metadata',
    ];

    protected $casts = [
        'fantasy_points_total' => 'decimal:2',
        'average_points' => 'decimal:4',
        'best_matchday_score' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }
}
