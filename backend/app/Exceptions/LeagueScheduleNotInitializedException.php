<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueScheduleNotInitializedException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The league competition has not been initialized for this matchday.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'league_schedule_not_initialized',
            'message' => $this->getMessage(),
        ], 409);
    }
}
