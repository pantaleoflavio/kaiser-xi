<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ChampionshipParticipantsMissingTeamsException extends ConflictHttpException
{
    public const CODE = 'championship_participants_missing_teams';

    public function __construct(public readonly int $missingTeamCount)
    {
        parent::__construct('Every league participant must create a fantasy team before the championship can be initialized.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => self::CODE,
            'message' => $this->getMessage(),
            'missing_team_count' => $this->missingTeamCount,
        ], 409);
    }
}
