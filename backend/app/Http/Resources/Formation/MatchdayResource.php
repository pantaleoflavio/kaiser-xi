<?php

namespace App\Http\Resources\Formation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchdayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'deadline' => $this->lineupDeadline(),
            'championship_state' => $this->when($this->championship_state !== null, $this->championship_state),
            'formation_allowed' => $this->when($this->formation_allowed !== null, (bool) $this->formation_allowed),
            'is_calculated' => $this->when($this->is_calculated !== null, (bool) $this->is_calculated),
            'can_calculate' => $this->when($this->can_calculate !== null, (bool) $this->can_calculate),
            'can_recalculate' => $this->when($this->can_recalculate !== null, (bool) $this->can_recalculate),
            'calculation_status' => $this->when($this->calculation_status !== null, $this->calculation_status),
            'is_waiting_for_calculation_unlock' => $this->when(
                $this->is_waiting_for_calculation_unlock !== null,
                (bool) $this->is_waiting_for_calculation_unlock,
            ),
        ];
    }
}
