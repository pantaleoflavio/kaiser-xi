<?php

namespace App\Models;

use App\Enums\CompetitionType;
use App\Models\Season;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealCompetition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'country_code',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'type' => CompetitionType::class,
    ];

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public static function normalizeCode(string $code): string
    {
        return str($code)
            ->trim()
            ->slug('_')
            ->lower()
            ->toString();
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn(string $value): string => self::normalizeCode($value),
        );
    }
}
