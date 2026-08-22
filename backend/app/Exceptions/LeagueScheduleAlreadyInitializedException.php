<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueScheduleAlreadyInitializedException extends ConflictHttpException
{
    public const CODE = 'league_schedule_already_initialized';

    public function __construct()
    {
        parent::__construct(
            'The head-to-head schedule has already been initialized.',
            null,
            0,
            ['X-Error-Code' => self::CODE],
        );
    }
}
