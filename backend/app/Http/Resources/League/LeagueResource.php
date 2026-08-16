<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->memberships->firstWhere('user_id', $request->user()->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'max_participants' => $this->max_participants,
            'season' => [
                'id' => $this->season->id,
                'name' => $this->season->name,
                'competition' => [
                    'id' => $this->season->realCompetition->id,
                    'name' => $this->season->realCompetition->name,
                ],
            ],
            'type' => [
                'key' => $this->type->key,
                'label' => $this->type->label,
            ],
            'status' => [
                'key' => $this->status->key,
                'label' => $this->status->label,
            ],
            'my_role' => $membership?->role?->key,
            'competition_initialized' => $this->hasStartedFantasyCompetition(),
            'competition_start_matchday_id' => $this->isNonHeadToHeadChampionship() ? $this->championship_start_matchday_id : $this->h2h_start_matchday_id,
        ];
    }
}
