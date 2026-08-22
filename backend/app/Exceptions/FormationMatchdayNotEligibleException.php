<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FormationMatchdayNotEligibleException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Formations may only be changed for the current matchday.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'formation_matchday_not_eligible',
            'message' => $this->getMessage(),
        ], 409);
    }
}
