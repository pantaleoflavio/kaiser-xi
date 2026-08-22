<?php

namespace App\Http\Requests\LeagueInvitation;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeagueInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:participant,co_commissioner'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses' => ['prohibited'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
            'used_count' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'league_id' => ['prohibited'],
            'league_role_id' => ['prohibited'],
            'target_role' => ['prohibited'],
        ];
    }
}
