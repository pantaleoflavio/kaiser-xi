<?php

namespace App\Http\Resources\League;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'initial_budget' => $this->initialFantasyBudget(),
            'release_refund_percentage' => $this->releaseRefundPercentage(),
            'max_roster_players' => $this->maxRosterPlayers(),
            'roster_role_limits' => $this->rosterRoleLimits(),
        ];
    }
}
