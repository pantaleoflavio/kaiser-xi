<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEligiblePlayersRequest extends FormRequest
{
    public const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search') && is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists('player_roles', 'key')],
            'club_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }
}
