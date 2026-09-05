<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Season;
use App\Services\Player\BuildPlayerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController extends Controller
{
    public function __invoke(Request $request, Player $player, BuildPlayerProfile $builder): JsonResponse
    {
        $validated = $request->validate(['season_id' => ['required', 'integer', 'exists:seasons,id']]);
        $season = Season::query()->findOrFail($validated['season_id']);

        return response()->json(['data' => $builder->execute($player, $season)]);
    }
}
