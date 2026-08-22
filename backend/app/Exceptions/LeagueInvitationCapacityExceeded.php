<?php

namespace App\Exceptions;

use RuntimeException;

class LeagueInvitationCapacityExceeded extends RuntimeException
{
    public static function forRemainingCapacity(): self
    {
        return new self('The max uses may not exceed the league remaining capacity.');
    }
}
