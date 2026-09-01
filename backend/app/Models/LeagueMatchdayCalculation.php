<?php

namespace App\Models;

use App\Enums\CalculationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMatchdayCalculation extends Model
{
    protected $fillable = [
        'league_id',
        'matchday_id',
        'status',
        'execution_token',
        'queued_at',
        'started_at',
        'failed_at',
        'failure_message',
        'calculated_at'
    ];

    protected $casts = [
        'status' => CalculationStatus::class,
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'failed_at' => 'datetime',
        'calculated_at' => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }
}
