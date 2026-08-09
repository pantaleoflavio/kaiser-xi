<?php

namespace App\Enums;

enum LeagueInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
