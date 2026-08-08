<?php

namespace App\Http\Requests\Formation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFormationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'formation_module_id' => ['required', 'integer', Rule::exists('formation_modules', 'id')],
            'starters' => ['required', 'array'],
            'starters.*' => ['required', 'integer', 'distinct', Rule::exists('fantasy_team_players', 'id')],
            'bench' => ['present', 'array'],
            'bench.*' => ['required', 'array:fantasy_team_player_id,order'],
            'bench.*.fantasy_team_player_id' => ['required', 'integer', 'distinct', Rule::exists('fantasy_team_players', 'id')],
            'bench.*.order' => ['required', 'integer', 'min:1', 'distinct'],
            'captain_fantasy_team_player_id' => ['nullable', 'integer', Rule::exists('fantasy_team_players', 'id')],
            'submitted_at' => ['prohibited'],
            'locked_at' => ['prohibited'],
            'is_confirmed' => ['prohibited'],
        ];
    }
}