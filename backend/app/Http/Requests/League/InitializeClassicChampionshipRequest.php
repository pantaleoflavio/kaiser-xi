<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;

class InitializeClassicChampionshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['start_matchday_id' => ['required', 'integer', 'exists:matchdays,id']];
    }
}
