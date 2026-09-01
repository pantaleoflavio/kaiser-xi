<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Formation\MatchdayResource;
use App\Models\League;
use App\Models\Matchday;
use App\Services\Matchday\LeagueMatchdayCalculationService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CalculateMatchdayController extends Controller
{
    public function __invoke(League $league, Matchday $matchday,  LeagueMatchdayCalculationService $calculations): MatchdayResource|JsonResponse
    {
        if ((int) $matchday->season_id !== (int) $league->season_id) {
            abort(404);
        }

        try {
            $calculations->reserve($league, $matchday);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        $matchday->refresh();
        MatchdayController::addCalculationCapabilities($matchday, $league, request()->user());

        return (new MatchdayResource($matchday))->response()->setStatusCode(202);
    }
}
