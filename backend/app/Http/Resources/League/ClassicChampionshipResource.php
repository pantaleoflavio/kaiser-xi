<?php

namespace App\Http\Resources\League;

use App\Models\Matchday;
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
            'start_matchday' => $this->championshipStartMatchday ? ['id' => $this->championshipStartMatchday->id, 'number' => $this->championshipStartMatchday->number, 'name' => $this->championshipStartMatchday->name] : null,
            'available_start_matchdays' => $this->hasInitializedChampionship() ? [] : Matchday::query()
                ->where('season_id', $this->season_id)
                ->where('starts_at', '>', now())
                ->orderBy('number')
                ->get()
                ->map(fn(Matchday $matchday): array => [
                    'id' => $matchday->id,
                    'number' => $matchday->number,
                    'name' => $matchday->name,
                    'starts_at' => $matchday->starts_at,
                ])->all(),
        ];
    }
}
