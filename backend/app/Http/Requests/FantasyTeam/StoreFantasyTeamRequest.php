<?php

namespace App\Http\Requests\FantasyTeam;

use Illuminate\Foundation\Http\FormRequest;

class StoreFantasyTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/'],
            'league_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'slug' => ['prohibited'],
            'logo_path' => ['prohibited'],
            'budget' => ['prohibited'],
            'remaining_budget' => ['prohibited'],
        ];
    }
}
