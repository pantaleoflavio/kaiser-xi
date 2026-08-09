<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FantasyRosterFullException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The fantasy team roster has reached its maximum active player count.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'roster_full', 'message' => $this->getMessage()], 409);
    }
}
