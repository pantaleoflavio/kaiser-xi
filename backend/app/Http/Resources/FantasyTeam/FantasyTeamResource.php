<?php

namespace App\Http\Resources\FantasyTeam;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FantasyTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'league_id' => $this->league_id,
            'budget' => $this->budget,
            'remaining_budget' => $this->remaining_budget,
            'owner' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'is_owned_by_current_user' => $request->user()?->id === $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
