<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LineupDeadlinePassedException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The formation deadline has passed.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'lineup_deadline_passed',
            'message' => $this->getMessage(),
        ], 409);
    }
}
