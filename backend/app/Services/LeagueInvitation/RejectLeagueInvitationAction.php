<?php

namespace App\Services\LeagueInvitation;

use App\Enums\LeagueInvitationStatus;
use App\Models\LeagueInvitation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectLeagueInvitationAction
{
    public function handle(LeagueInvitation $invitation): LeagueInvitation
    {
        return DB::transaction(function () use ($invitation): LeagueInvitation {
            $locked = LeagueInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isAvailable()) {
                throw new ConflictHttpException($locked->isExpired() ? 'Invitation has expired.' : 'Invitation has already been processed.');
            }
            $locked->forceFill(['status' => LeagueInvitationStatus::Rejected])->save();

            return $locked;
        });
    }
}
