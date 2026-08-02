<?php

namespace App\Http\Resources\LeagueInvitation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueInvitationPreviewResource extends JsonResource
{
   public function toArray(Request $request): array
    {
        $league = $this->league;

        return [
            'code' => $this->code,
            'id' => $this->id,
            'status' => $this->isExpired() && $this->isActive() ? 'expired' : $this->status->value,
            'role' => ['key' => $this->role->key, 'label' => $this->role->label],
            'is_available' => $this->isAvailable(),
            'expires_at' => $this->expires_at?->toJSON(),
            'remaining_uses' => $this->remainingUses(),
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
                'type' => ['key' => $league->type->key, 'label' => $league->type->label],
                'status' => ['key' => $league->status->key, 'label' => $league->status->label],
                'season' => [
                    'id' => $league->season->id,
                    'name' => $league->season->name,
                    'competition' => [
                        'id' => $league->season->realCompetition->id,
                        'name' => $league->season->realCompetition->name,
                    ],
                ],
                'current_member_count' => $league->memberships_count,
                'max_participants' => $league->max_participants,
            ],
            'current_user_is_member' => $league->memberships->contains('user_id', $request->user()->id),
            'creator' => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'available_actions' => $this->isAvailable() ? ['accept', 'reject'] : [],
        ];
    }
}
