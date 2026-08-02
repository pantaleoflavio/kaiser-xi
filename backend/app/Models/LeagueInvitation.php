<?php

namespace App\Models;

use App\Enums\LeagueInvitationStatus;
use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'created_by_user_id',
        'invited_user_id',
        'league_role_id',
        'code',
        'status',
        'max_uses',
        'used_count',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'status' => LeagueInvitationStatus::class,
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(LeagueRole::class, 'league_role_id');
    }

    public function isActive(): bool
    {
        return $this->status === LeagueInvitationStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function isAvailable(): bool
    {
        return $this->isActive() && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function remainingUses(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - $this->used_count);
    }
}
