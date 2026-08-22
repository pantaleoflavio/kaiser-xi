<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeagueMatchdayCalculation extends Model
{
    protected $fillable = ['league_id', 'matchday_id', 'calculated_at'];

    protected $casts = ['calculated_at' => 'datetime'];
}
