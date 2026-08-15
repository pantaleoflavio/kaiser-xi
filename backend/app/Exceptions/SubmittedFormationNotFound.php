<?php

namespace App\Exceptions;

use DomainException;

final class SubmittedFormationNotFound extends DomainException
{
    public static function forTeamAndMatchday(int $fantasyTeamId, int $matchdayId): self
    {
        return new self("No submitted formation exists for fantasy team {$fantasyTeamId} and matchday {$matchdayId}.");
    }
}
