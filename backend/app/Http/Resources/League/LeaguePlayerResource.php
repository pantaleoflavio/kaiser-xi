<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaguePlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fantasyTeam = $this->market_team_id === null ? null : [
            'id' => $this->market_team_id,
            'name' => $this->market_team_name,
        ];

        return [
            'id' => $this->player_id,
            'name' => $this->player?->display_name,
            'club' => [
                'id' => $this->seasonClub?->real_club_id,
                'name' => $this->seasonClub?->display_name ?? $this->seasonClub?->realClub?->name,
            ],
            'position' => [
                'key' => $this->playerRole?->key,
                'label' => $this->playerRole?->label,
            ],
            'is_free_agent' => $fantasyTeam === null,
            'fantasy_team' => $fantasyTeam,
        ];
    }
}
