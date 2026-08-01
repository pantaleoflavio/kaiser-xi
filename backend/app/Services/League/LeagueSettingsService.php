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
                LeagueSetting::MAX_ROSTER_PLAYERS => LeagueSetting::DEFAULT_MAX_ROSTER_PLAYERS,
            ] as $key => $value
        ) {
            $league->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => LeagueSetting::integerPayload($key, $value)],
            );
        }

        $league->settings()->updateOrCreate(
            ['key' => LeagueSetting::ROSTER_ROLE_LIMITS],
            ['value' => LeagueSetting::roleLimitsPayload(LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS)],
        );
    }

    /** @param array{initial_budget?: int, release_refund_percentage?: int, max_roster_players?: int, roster_role_limits?: array<string, int>} $settings */
    public function update(League $league, array $settings): League
    {
        DB::transaction(function () use ($league, $settings): void {
            foreach ([
                LeagueSetting::INITIAL_BUDGET => $settings['initial_budget'] ?? null,
                LeagueSetting::RELEASE_REFUND_PERCENTAGE => $settings['release_refund_percentage'] ?? null,
                    LeagueSetting::MAX_ROSTER_PLAYERS => $settings['max_roster_players'] ?? null,
                ] as $key => $value
            ) {
                if ($value === null) {
                    continue;
                }

                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $league->id, 'key' => $key],
                    ['value' => LeagueSetting::integerPayload($key, (int) $value)],
                );
            }

            if (array_key_exists('roster_role_limits', $settings)) {
                LeagueSetting::query()->updateOrCreate(
                    ['league_id' => $league->id, 'key' => LeagueSetting::ROSTER_ROLE_LIMITS],
                    ['value' => LeagueSetting::roleLimitsPayload($settings['roster_role_limits'])],
                );
            }
        });

        return $league->refresh();
    }
}