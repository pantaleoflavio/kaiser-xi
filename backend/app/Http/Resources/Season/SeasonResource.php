<?php

namespace App\Http\Resources\Season;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at->toDateString(),
            'is_active' => $this->is_active,
            'competition' => [
                'id' => $this->realCompetition->id,
                'name' => $this->realCompetition->name,
                'code' => $this->realCompetition->code,
            ],
        ];
    }
}
