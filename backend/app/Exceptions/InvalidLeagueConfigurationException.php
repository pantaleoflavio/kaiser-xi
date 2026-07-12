<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvalidLeagueConfigurationException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = 'The league roster or budget configuration is invalid.')
    {
        parent::__construct($message);
    }
}
