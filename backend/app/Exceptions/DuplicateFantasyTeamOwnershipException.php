<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DuplicateFantasyTeamOwnershipException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The authenticated user already owns a fantasy team in this league.');
    }
}
