<?php

namespace App\Exceptions;

use DomainException;

class IncompleteFantasyMatchScoreException extends DomainException
{
    public function __construct(int $fantasyTeamId, int $matchdayId)
    {
        parent::__construct("A calculated score is required for fantasy team {$fantasyTeamId} and matchday {$matchdayId}.");
    }
}
