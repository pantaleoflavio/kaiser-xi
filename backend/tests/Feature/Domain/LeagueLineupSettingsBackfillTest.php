<?php

namespace Tests\Feature\Domain;

use App\Models\League;
use App\Models\LeagueSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueLineupSettingsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_adds_missing_defaults_without_overwriting_existing_values(): void
    {
        $league = League::factory()->create();
        $league->settings()->create([
            'key' => LeagueSetting::BENCH_SIZE,
            'value' => LeagueSetting::integerPayload(LeagueSetting::BENCH_SIZE, 4),
        ]);

    $migrationFiles = glob(
        base_path(
            'database/migrations/*_backfill_lineup_rule_league_settings.php'
        )
    );
    
    $this->assertCount(
        1,
        $migrationFiles,
        'Expected exactly one lineup-rule league-settings backfill migration.'
    );

    $migration = require $migrationFiles[0];

    $migration->up();
    $migration->up();

    $this->assertSame(
        4,
        $league->refresh()->benchSize()
    );

    foreach ([
        LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
        LeagueSetting::BENCH_SIZE,
        LeagueSetting::BENCH_ROLE_LIMITS,
        LeagueSetting::MAX_SUBSTITUTIONS,
        LeagueSetting::SUBSTITUTION_ORDER_MODE,
        LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
        LeagueSetting::CAPTAIN_ENABLED,
        LeagueSetting::VICE_CAPTAIN_ENABLED,
    ] as $key) {
        $this->assertSame(
            1,
            $league->settings()
                ->where('key', $key)
                ->count()
        );
    }
    }
}