<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UnsupportedInitialBudgetChangeException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Initial budget cannot be changed after fantasy teams exist.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'initial_budget_change_unsupported', 'message' => $this->getMessage()], 409);
    }
}