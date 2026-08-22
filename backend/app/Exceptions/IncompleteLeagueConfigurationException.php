<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class IncompleteLeagueConfigurationException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = 'The league configuration is incomplete or invalid.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => 'league_configuration_incomplete', 'message' => $this->getMessage()], 422);
    }
}
