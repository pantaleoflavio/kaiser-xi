<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StandingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->league?->isFormulaOne()) {
            return [
                'position' => $this->position,
                'fantasy_team' => ['id' => $this->fantasyTeam->id, 'name' => $this->fantasyTeam->name, 'slug' => $this->fantasyTeam->slug],
                'played' => $this->played,
                'championship_points' => $this->championship_points,
                'wins' => $this->wins,
                'podiums' => $this->podiums,
                'best_finish' => $this->best_finish,
                'fantasy_points_total' => $this->fantasy_points_total,
                'average_fantasy_points' => $this->average_points,
            ];
        }

        if ($this->league?->isClassic()) {
            return [
                'position' => $this->position,
                'fantasy_team' => [
                    'id' => $this->fantasyTeam->id,
                    'name' => $this->fantasyTeam->name,
                    'slug' => $this->fantasyTeam->slug
                ],
                'played' => $this->played,
                'total_points' => $this->fantasy_points_total,
                'average_points' => $this->average_points,
                'best_matchday_score' => $this->best_matchday_score,
            ];
        }

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
