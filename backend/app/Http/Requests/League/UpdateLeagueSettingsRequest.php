<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;

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
            'remaining_budget' => ['prohibited'],
            'league_id' => ['prohibited'],
        ];
    }
}
