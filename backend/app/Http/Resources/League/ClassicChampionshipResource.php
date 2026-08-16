<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassicChampionshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'initialized' => $this->hasInitializedChampionship(),
            'started_at' => $this->championship_started_at,
            'participant_count' => $this->hasInitializedChampionship() ? $this->championship_participants_count : $this->fantasy_teams_count,
            'max_participants' => $this->max_participants,
            'start_matchday' => $this->championshipStartMatchday ? ['id' => $this->championshipStartMatchday->id, 'number' => $this->championshipStartMatchday->number, 'name' => $this->championshipStartMatchday->name] : null
        ];
    }
}
