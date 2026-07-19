<?php

namespace App\Http\Resources\Player;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EligiblePlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->player_id,
            'name' => $this->player?->display_name,
            'role' => [
                'key' => $this->playerRole?->key,
                'label' => $this->playerRole?->label,
            ],
            'club' => [
                'id' => $this->seasonClub?->id,
                'name' => $this->seasonClub?->display_name ?? $this->seasonClub?->realClub?->name,
                'real_club_id' => $this->seasonClub?->real_club_id,
            ],
            'quotation' => $this->quotation === null ? null : (float) $this->quotation,
            'availability' => 'available',
        ];
    }
}