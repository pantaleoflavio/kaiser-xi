<?php

namespace App\Exceptions;

use DomainException;

class TradeConflictException extends DomainException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
