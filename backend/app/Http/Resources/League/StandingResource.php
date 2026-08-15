<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'fantasy_team' => [
                'id' => $this->fantasyTeam->id,
                'name' => $this->fantasyTeam->name,
                'slug' => $this->fantasyTeam->slug,
            ],
            'played' => $this->played,
            'wins' => $this->wins,
            'draws' => $this->draws,
            'losses' => $this->losses,
            'goals_for' => $this->goals_for,
            'goals_against' => $this->goals_against,
            'goal_difference' => $this->goals_for - $this->goals_against,
            'points' => $this->points_total,
        ];
    }
}
