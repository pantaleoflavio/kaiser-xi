<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueAlreadyActiveException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The league is already active and cannot be activated again.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'league_already_active', 'message' => $this->getMessage()], 409);
    }
}
