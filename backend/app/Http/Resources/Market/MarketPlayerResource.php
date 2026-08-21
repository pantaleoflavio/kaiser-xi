<?php

namespace App\Http\Resources\Market;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assigned = $this->market_team_id !== null;
        return [
            'id' => $this->player_id,
            'name' => $this->player?->display_name,
            'role' => ['key' => $this->playerRole?->key, 'label' => $this->playerRole?->label],
            'club' => ['id' => $this->seasonClub?->real_club_id, 'name' => $this->seasonClub?->display_name ?? $this->seasonClub?->realClub?->name],
            'quotation' => $this->quotation === null ? null : (float) $this->quotation,
            'assignment_state' => $assigned ? 'assigned' : 'unassigned',
            'fantasy_team' => $assigned ? ['id' => $this->market_team_id, 'name' => $this->market_team_name, 'is_own' => $this->market_team_user_id === $request->user()?->id] : null,
        ];
    }
}
