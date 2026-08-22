<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LeagueMembership extends Pivot
{
    protected $table = 'league_user';

    protected $fillable = [
        'league_id',
        'user_id',
        'league_role_id',
        'joined_at',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(LeagueRole::class, 'league_role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
