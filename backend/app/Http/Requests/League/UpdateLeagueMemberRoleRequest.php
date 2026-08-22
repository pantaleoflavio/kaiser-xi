<?php

namespace App\Http\Requests\League;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeagueMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(['participant', 'co_commissioner'])],
            'league_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'league_role_id' => ['prohibited'],
            'commissioner_user_id' => ['prohibited'],
        ];
    }
}
