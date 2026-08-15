<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormationPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'formation_id',
        'fantasy_team_player_id',
        'player_id',
        'player_role_id',
        'slot_type',
        'position_index',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function fantasyTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(FantasyTeamPlayer::class);
    }

    public function playerRole(): BelongsTo
    {
        return $this->belongsTo(PlayerRole::class);
    }
}
