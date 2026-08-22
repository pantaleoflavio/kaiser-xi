<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InsufficientFantasyBudgetException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The fantasy team does not have enough remaining budget.');
    }
}
