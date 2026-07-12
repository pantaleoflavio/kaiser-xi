<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlayerAlreadyAssignedInLeagueException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The player is already assigned to an active roster in this league.');
    }
}
