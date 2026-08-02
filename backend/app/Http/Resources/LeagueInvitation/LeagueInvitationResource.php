<?php

namespace App\Http\Resources\LeagueInvitation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueInvitationResource extends JsonResource
{
   public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->isExpired() && $this->isActive() ? 'expired' : $this->status->value,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'remaining_uses' => $this->remainingUses(),
            'expires_at' => $this->expires_at?->toJSON(),
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'is_exhausted' => $this->isExhausted(),
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at?->toJSON(),
            'creator' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'recipient' => $this->whenLoaded('invitedUser', fn() => [
                'id' => $this->invitedUser->id,
                'name' => $this->invitedUser->name,
            ]),
            'role' => $this->whenLoaded('role', fn() => [
                'key' => $this->role->key,
                'label' => $this->role->label,
            ]),
            'league' => $this->whenLoaded('league', fn() => [
                'id' => $this->league->id,
                'name' => $this->league->name,
            ]),
            'available_actions' => $this->isAvailable() ? ['accept', 'reject'] : [],
        ];
    }
}