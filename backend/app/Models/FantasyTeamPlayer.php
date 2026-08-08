<?php

namespace App\Models;

use App\Models\FantasyTeam;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FantasyTeamPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'fantasy_team_id',
        'player_id',
        'assigned_by_user_id',
        'released_by_user_id',
        'purchase_price',
        'assigned_at',
        'released_at',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function formationPlayers(): HasMany
    {
        return $this->hasMany(FormationPlayer::class);
    }
}
