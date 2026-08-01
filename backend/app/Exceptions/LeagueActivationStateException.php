<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeagueActivationStateException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The league state does not permit activation.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'league_activation_state_invalid', 'message' => $this->getMessage()], 409);
    }
}
