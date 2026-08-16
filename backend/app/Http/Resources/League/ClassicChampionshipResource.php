<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassicChampionshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'initialized' => $this->hasInitializedClassicChampionship(),
            'started_at' => $this->classic_started_at,
            'participant_count' => $this->hasInitializedClassicChampionship() ? $this->classic_participants_count : $this->fantasy_teams_count,
            'max_participants' => $this->max_participants,
            'start_matchday' => $this->classicStartMatchday ? ['id' => $this->classicStartMatchday->id, 'number' => $this->classicStartMatchday->number, 'name' => $this->classicStartMatchday->name] : null
        ];
    }
}
