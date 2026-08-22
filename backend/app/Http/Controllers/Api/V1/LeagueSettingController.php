<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\League\UpdateLeagueSettingsRequest;
use App\Http\Resources\League\LeagueSettingsResource;
use App\Models\League;
use App\Services\League\LeagueSettingsService;

class LeagueSettingController extends Controller
{
    public function __construct(private LeagueSettingsService $settingsService) {}

    public function show(League $league): LeagueSettingsResource
    {
        return new LeagueSettingsResource($league);
    }

    public function update(UpdateLeagueSettingsRequest $request, League $league): LeagueSettingsResource
    {
        return new LeagueSettingsResource($this->settingsService->update($league, $request->validated()));
    }
}
