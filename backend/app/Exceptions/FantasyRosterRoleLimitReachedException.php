<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FantasyRosterRoleLimitReachedException extends ConflictHttpException
{
    public function __construct(string $roleKey)
    {
        parent::__construct("The fantasy team roster has reached the active player limit for role [{$roleKey}].");
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'roster_role_limit_reached', 'message' => $this->getMessage()], 409);
    }
}
