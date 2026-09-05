<?php

namespace App\Models;

use App\Models\PlayerExternalIdentity;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'display_name',
        'slug',
        'birth_date',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function playerSeasonRegistrations(): HasMany
    {
        return $this->hasMany(PlayerSeasonRegistration::class);
    }

    public function externalIdentities(): HasMany
    {
        return $this->hasMany(PlayerExternalIdentity::class);
    }
}
