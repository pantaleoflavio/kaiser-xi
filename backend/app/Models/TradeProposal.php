<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TradeProposalStatus;

class TradeProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'from_team_id',
        'to_team_id',
        'offered_fantasy_team_player_id',
        'requested_fantasy_team_player_id',
        'cash_paid_by_team_id',
        'cash_amount',
        'status',
        'message',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'cash_amount' => 'integer',
        'expires_at' => 'datetime',
        'status' => TradeProposalStatus::class,
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'to_team_id');
    }

    public function cashPaidByTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'cash_paid_by_team_id');
    }

    public function offeredAssignment(): BelongsTo
    {
        return $this->belongsTo(FantasyTeamPlayer::class, 'offered_fantasy_team_player_id');
    }

    public function requestedAssignment(): BelongsTo
    {
        return $this->belongsTo(FantasyTeamPlayer::class, 'requested_fantasy_team_player_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TradeProposalItem::class);
    }
}
