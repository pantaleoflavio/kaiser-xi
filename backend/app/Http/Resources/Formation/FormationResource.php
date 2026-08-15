<?php

namespace App\Http\Resources\Formation;

use App\Models\FormationPlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'formation_module' => [
                'id' => $this->formationModule->id,
                'name' => $this->formationModule->name,
                'requirements' => $this->formationModule->requirements
                    ->mapWithKeys(fn($requirement): array => [$requirement->playerRole->key => (int) $requirement->required_count]),
            ],
            'starters' => $this->players->where('slot_type', 'starter')->sortBy('position_index')->values()
                ->map(fn(FormationPlayer $player): array => $this->player($player))->all(),
            'bench' => $this->players->where('slot_type', 'bench')->sortBy('position_index')->values()
                ->map(fn(FormationPlayer $player): array => $this->player($player))->all(),
            'submitted' => $this->submitted_at !== null,
            'submitted_at' => $this->submitted_at,
            'matchday' => [
                'id' => $this->matchday->id,
                'number' => $this->matchday->number,
                'name' => $this->matchday->name,
                'deadline' => $this->matchday->starts_at,
            ],
        ];
    }

    private function player(FormationPlayer $formationPlayer): array
    {
        return [
            'fantasy_team_player_id' => $formationPlayer->fantasy_team_player_id,
            'player' => ['id' => $formationPlayer->player_id, 'name' => $formationPlayer->player->display_name, 'role' => $formationPlayer->playerRole->key],
            'order' => $formationPlayer->position_index,
        ];
    }
}
