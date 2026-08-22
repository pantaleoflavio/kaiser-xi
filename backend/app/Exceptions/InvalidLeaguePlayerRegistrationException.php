<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvalidLeaguePlayerRegistrationException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('The player is not actively registered for this league season.');
    }
}
