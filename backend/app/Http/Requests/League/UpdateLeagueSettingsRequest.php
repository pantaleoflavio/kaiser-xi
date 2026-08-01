<?php

namespace App\Http\Requests\League;

use App\Models\League;
use App\Models\LeagueSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLeagueSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'initial_budget' => ['sometimes', 'required', 'integer', 'min:0'],
            'release_refund_percentage' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'max_roster_players' => ['sometimes', 'required', 'integer', 'min:1'],
            'roster_role_limits' => [
                'sometimes',
                'required',
                'array:' . implode(',', LeagueSetting::PLAYER_ROLE_KEYS),
                'required_array_keys:' . implode(',', LeagueSetting::PLAYER_ROLE_KEYS),
            ],
            'roster_role_limits.*' => ['required', 'integer', 'min:0'],
            'remaining_budget' => ['prohibited'],
            'league_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $league = $this->route('league');
            if (! $league instanceof League) {
                return;
            }

            $maximum = (int) $this->input('max_roster_players', $league->maxRosterPlayers());
            $limits = $this->input('roster_role_limits', $league->rosterRoleLimits());

            if (array_sum($limits) < $maximum) {
                $validator->errors()->add(
                    'roster_role_limits',
                    'The sum of roster role limits must be greater than or equal to the maximum roster players.'
                );
            }
        }];
    }
}
