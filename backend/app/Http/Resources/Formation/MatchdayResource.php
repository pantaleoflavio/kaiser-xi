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
            'deadline' => $this->starts_at,
            'championship_state' => $this->when($this->championship_state !== null, $this->championship_state),
            'formation_allowed' => $this->when($this->formation_allowed !== null, (bool) $this->formation_allowed),
        ];
    }
}
