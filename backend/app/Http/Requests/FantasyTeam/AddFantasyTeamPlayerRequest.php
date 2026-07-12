<?php

namespace App\Http\Requests\FantasyTeam;

use App\Models\FantasyTeamPlayer;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddFantasyTeamPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player_id' => ['required', 'integer', Rule::exists('players', 'id')],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'league_id' => ['prohibited'],
            'fantasy_team_id' => ['prohibited'],
            'assigned_by_user_id' => ['prohibited'],
            'remaining_budget' => ['prohibited'],
            'refund_amount' => ['prohibited'],
            'released_at' => ['prohibited'],
            'assigned_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $league = $this->route('league');
            $team = $this->route('fantasyTeam');
            $playerId = (int) $this->input('player_id');
            $purchasePrice = (int) $this->input('purchase_price');

            if (! $league || ! $team || $playerId <= 0 || $validator->errors()->isNotEmpty()) {
                return;
            }

            $eligible = PlayerSeasonRegistration::query()
                ->where('player_id', $playerId)
                ->where('is_active', true)
                ->whereNull('released_at')
                ->whereHas('seasonClub', fn ($query) => $query->where('season_id', $league->season_id))
                ->exists();

            if (! $eligible) {
                $validator->errors()->add('player_id', 'The player is not actively registered for this league season.');
            }

            if (FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('player_id', $playerId)->exists()) {
                $validator->errors()->add('player_id', 'The player is already assigned to an active roster in this league.');
            }

            if ($purchasePrice > (int) $team->remaining_budget) {
                $validator->errors()->add('purchase_price', 'The fantasy team does not have enough remaining budget.');
            }
        }];
    }
}
