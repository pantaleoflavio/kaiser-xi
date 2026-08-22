<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IncompatibleRosterSizeException extends ConflictHttpException
{
    public function __construct(int $minimum)
    {
        parent::__construct("The maximum roster size cannot be lower than the largest active roster ({$minimum}).");
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'roster_size_incompatible', 'message' => $this->getMessage()], 409);
    }
}
