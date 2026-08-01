<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueMutabilityFlagsLockedException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Rule mutability flags cannot be changed after activation.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'league_mutability_flags_locked', 'message' => $this->getMessage()], 409);
    }
}