<?php

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradeProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiving_fantasy_team_id' => ['required', 'integer'],
            'offered_fantasy_team_player_id' => ['required', 'integer'],
            'requested_fantasy_team_player_id' => ['required', 'integer'],
            'cash_from_fantasy_team_id' => ['nullable', 'integer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
