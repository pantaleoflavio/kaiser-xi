<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'league_type_id' => ['required', 'integer', 'exists:league_types,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'max_participants' => ['sometimes', 'integer', 'min:2', 'max:100'],
        ];
    }
}
