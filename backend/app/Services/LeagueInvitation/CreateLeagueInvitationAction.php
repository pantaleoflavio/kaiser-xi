<?php

namespace App\Services\LeagueInvitation;

use App\Enums\LeagueInvitationStatus;
use App\Exceptions\LeagueScheduleAlreadyInitializedException;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueRole;
use App\Models\User;
use App\Services\LeagueInvitation\InvitationCodeGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateLeagueInvitationAction
{
    public function __construct(private readonly InvitationCodeGenerator $codeGenerator) {}

    public function handle(League $league, User $creator, array $data): LeagueInvitation
    {
        return DB::transaction(function () use ($league, $creator, $data): LeagueInvitation {
            $lockedLeague = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            if ($lockedLeague->hasStartedFantasyCompetition()) {
                throw new LeagueScheduleAlreadyInitializedException;
            }
            $recipient = User::query()->where('email', $data['email'])->firstOrFail();

            if ($lockedLeague->memberships()->where('user_id', $recipient->id)->exists()) {
                throw new ConflictHttpException('User is already a member of this league.', null, 0, ['X-Error-Code' => 'already_a_member']);
            }

            if ($lockedLeague->invitations()->where('invited_user_id', $recipient->id)->where('status', LeagueInvitationStatus::Pending)->exists()) {
                throw new ConflictHttpException('A pending invitation already exists.', null, 0, ['X-Error-Code' => 'duplicate_active_invitation']);
            }

            $role = LeagueRole::query()->where('key', $data['role'])->firstOrFail();

            return LeagueInvitation::query()->create([
                'league_id' => $lockedLeague->id,
                'created_by_user_id' => $creator->id,
                'invited_user_id' => $recipient->id,
                'league_role_id' => $role->id,
                'code' => $this->codeGenerator->generate(),
                'status' => LeagueInvitationStatus::Pending,
                'max_uses' => 1,
                'used_count' => 0,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
        });
    }
}
