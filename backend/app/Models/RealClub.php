<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealClub extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'slug',
        'country_code',
        'logo_path',
    ];

    public function seasonClubs(): HasMany
    {
        return $this->hasMany(SeasonClub::class);
    }

    public function externalIdentities(): HasMany
    {
        return $this->hasMany(RealClubExternalIdentity::class);
    }
}
