<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\LeagueSetting;
use Illuminate\Support\Facades\DB;

class LeagueSettingsService
{
    public function initializeDefaults(League $league): void
    {
        foreach ([
            LeagueSetting::INITIAL_BUDGET => LeagueSetting::DEFAULT_INITIAL_BUDGET,
            LeagueSetting::RELEASE_REFUND_PERCENTAGE => LeagueSetting::DEFAULT_RELEASE_REFUND_PERCENTAGE,
        ] as $key => $value) {
            $league->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::integerPayload($key, $value)],
            );
        }
    }

    /** @param array{initial_budget?: int, release_refund_percentage?: int} $settings */
    public function update(League $league, array $settings): League
    {
        DB::transaction(function () use ($league, $settings): void {
            foreach ([
                LeagueSetting::INITIAL_BUDGET => $settings['initial_budget'] ?? null,
                LeagueSetting::RELEASE_REFUND_PERCENTAGE => $settings['release_refund_percentage'] ?? null,
            ] as $key => $value) {
                if ($value === null) {
                    continue;
                }

                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $league->id, 'key' => $key],
                    ['value' => LeagueSetting::integerPayload($key, (int) $value)]
                );
            }
        });

        return $league->refresh();
    }
}