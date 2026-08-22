<?php

namespace App\Http\Resources\FantasyTeam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FantasyTeamPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registration = $this->player?->playerSeasonRegistrations?->first();

        return [
            'id' => $this->id,
            'player' => [
                'id' => $this->player_id,
                'name' => $this->player?->display_name,
                'role' => $registration?->playerRole?->key,
            ],
            'purchase_price' => (int) $this->purchase_price,
            'assigned_at' => $this->assigned_at,
            'released_at' => $this->released_at,
        ];
    }
}
