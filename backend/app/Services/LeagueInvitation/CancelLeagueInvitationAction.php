<?php

namespace App\Services\LeagueInvitation;

use App\Enums\LeagueInvitationStatus;
use App\Models\LeagueInvitation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelLeagueInvitationAction
{
    public function handle(LeagueInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = LeagueInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isAvailable()) {
                throw new ConflictHttpException('Only a pending, unexpired invitation may be revoked.');
            }
            $locked->forceFill(['status' => LeagueInvitationStatus::Revoked])->save();
        });
    }
}