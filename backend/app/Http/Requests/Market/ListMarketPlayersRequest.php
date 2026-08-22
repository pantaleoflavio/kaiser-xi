<?php

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMarketPlayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) $this->merge(['search' => trim($this->input('search'))]);
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists('player_roles', 'key')],
            'club_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'fantasy_team_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'assignment_state' => ['sometimes', 'nullable', Rule::in(['assigned', 'unassigned'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
