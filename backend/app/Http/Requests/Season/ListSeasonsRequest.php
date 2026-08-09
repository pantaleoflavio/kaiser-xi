<?php

namespace App\Http\Requests\Season;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSeasonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->query->has('active')) {
            return;
        }

        $active = filter_var(
            $this->query('active'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($active !== null) {
            $this->merge([
                'active' => $active,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'active' => ['sometimes', 'boolean'],
            'real_competition_id' => [
                'sometimes',
                'integer',
                Rule::exists('real_competitions', 'id'),
            ],
        ];
    }
}
