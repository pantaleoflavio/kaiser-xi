<?php

namespace App\Services\LeagueInvitation;

use App\Enums\LeagueInvitationStatus;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueMembership;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcceptLeagueInvitationAction
{
    public function handle(LeagueInvitation $invitation, User $user): LeagueMembership
    {
        try {
            return DB::transaction(function () use ($invitation, $user): LeagueMembership {
                $locked = LeagueInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                if (! $locked->isAvailable()) {
                    throw new ConflictHttpException($locked->isExpired() ? 'Invitation has expired.' : 'Invitation has already been processed.');
                }
                if ($locked->invited_user_id !== $user->id) {
                    abort(404);
                }

                $league = League::query()->whereKey($locked->league_id)->lockForUpdate()->firstOrFail();

                if ($league->memberships()->where('user_id', $user->id)->exists()) {
                    throw new ConflictHttpException('User is already a member of this league.');
                }

                if ($league->memberships()->count() >= $league->max_participants) {
                    throw new ConflictHttpException('League is full.');
                }

                $membership = LeagueMembership::query()->create([
                    'league_id' => $league->id,
                    'user_id' => $user->id,
                    'league_role_id' => $locked->league_role_id,
                    'joined_at' => now(),
                ]);

                $locked->forceFill(['status' => LeagueInvitationStatus::Accepted, 'used_count' => 1])->save();

                return $membership->load(['league', 'role', 'user']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('User is already a member of this league.', $exception);
        }
    }
}
