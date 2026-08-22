<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Season\ListSeasonsRequest;
use App\Http\Resources\Season\SeasonResource;
use App\Models\Season;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SeasonController extends Controller
{
    public function index(ListSeasonsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $seasons = Season::query()
            ->with('realCompetition:id,name,code')
            ->when(
                array_key_exists('active', $filters),
                fn ($query) => $query->where('is_active', $request->boolean('active'))
            )
            ->when(
                isset($filters['real_competition_id']),
                fn ($query) => $query->where('real_competition_id', $filters['real_competition_id'])
            )
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->orderBy('id')
            ->get();

        return SeasonResource::collection($seasons);
    }
}
