<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RealClubExternalIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'real_club_id',
        'provider',
        'external_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $identity): void {
            $identity->provider = mb_strtolower(trim((string) $identity->provider));

            Validator::make($identity->attributesToArray(), [
                'provider' => ['required', 'string', 'max:255'],
                'external_id' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('real_club_external_identities', 'external_id')
                        ->where(fn($query) => $query->where('provider', $identity->provider))
                        ->ignore($identity->getKey()),
                ],
            ])->validate();
        });
    }

    public function realClub(): BelongsTo
    {
        return $this->belongsTo(RealClub::class);
    }
}
