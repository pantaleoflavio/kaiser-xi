<?php

namespace App\Rules;

use App\Models\Matchday;
use App\Models\PlayerSeasonRegistration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlayerRegistrationMatchesMatchdaySeason implements ValidationRule
{
    public function __construct(private readonly mixed $matchdayId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $registration = PlayerSeasonRegistration::query()->with('seasonClub:id,season_id')->find($value);
        $matchday = Matchday::query()->find($this->matchdayId);

        if (! $registration || ! $matchday) {
            return;
        }

        if ($registration->seasonClub?->season_id !== $matchday->season_id) {
            $fail(__('admin.validation.player_scores.season_mismatch'));
        }
    }
}
