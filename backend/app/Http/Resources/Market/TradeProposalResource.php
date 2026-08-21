<?php

namespace App\Http\Resources\Market;

use App\Enums\TradeProposalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        $pending = $this->status === TradeProposalStatus::Pending;
        $player = static fn($assignment): array => ['assignment_id' => $assignment->id, 'id' => $assignment->player->id, 'name' => $assignment->player->display_name];
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'proposing_fantasy_team' => ['id' => $this->fromTeam->id, 'name' => $this->fromTeam->name],
            'receiving_fantasy_team' => ['id' => $this->toTeam->id, 'name' => $this->toTeam->name],
            'offered_player' => $player($this->offeredAssignment),
            'requested_player' => $player($this->requestedAssignment),
            'cash_from_fantasy_team' => $this->cashPaidByTeam ? ['id' => $this->cashPaidByTeam->id, 'name' => $this->cashPaidByTeam->name] : null,
            'cash_amount' => (float) $this->cash_amount,
            'created_at' => $this->created_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'cancelled_at' => $this->cancelled_at,
            'capabilities' => ['can_accept' => $pending && $this->toTeam->user_id === $userId, 'can_reject' => $pending && $this->toTeam->user_id === $userId, 'can_cancel' => $pending && $this->fromTeam->user_id === $userId],
        ];
    }
}
