<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IncompatibleRosterRoleLimitException extends ConflictHttpException
{
    public function __construct(string $role, int $minimum)
    {
        parent::__construct("The [{$role}] limit cannot be lower than the largest active count ({$minimum}).");
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'roster_role_limit_incompatible', 'message' => $this->getMessage()], 409);
    }
}
