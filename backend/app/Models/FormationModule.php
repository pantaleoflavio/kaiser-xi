<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormationModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'is_active',
    ];

    //
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(FormationModuleRequirement::class);
    }

    public function requiredPlayersCount(): int
    {
        if ($this->relationLoaded('requirements')) {
            return (int) $this->requirements->sum('required_count');
        }

        return (int) $this->requirements()->sum('required_count');
    }
}
