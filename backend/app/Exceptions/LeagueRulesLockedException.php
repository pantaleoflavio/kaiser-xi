<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueRulesLockedException extends ConflictHttpException
{
    public function __construct(string $group)
    {
        parent::__construct("The league rule group [{$group}] is locked in its current lifecycle state.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'league_rules_locked', 'message' => $this->getMessage()], 409);
    }
}