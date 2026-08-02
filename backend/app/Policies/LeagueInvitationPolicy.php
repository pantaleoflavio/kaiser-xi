<?php

namespace App\Policies;

use App\Models\LeagueInvitation;
use App\Models\User;

class LeagueInvitationPolicy
{
    public function viewRecipientInvitation(User $user, LeagueInvitation $invitation): bool
    {
        return $invitation->invited_user_id === $user->id;
    }

    public function accept(User $user, LeagueInvitation $invitation): bool
    {
        return $this->viewRecipientInvitation($user, $invitation);
    }

    public function reject(User $user, LeagueInvitation $invitation): bool
    {
        return $this->viewRecipientInvitation($user, $invitation);
    }
}
