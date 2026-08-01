<?php

namespace Tests\Feature\Domain;

use App\Models\League;
use App\Models\LeagueSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueMutabilityBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_is_idempotent_and_does_not_overwrite_existing_values(): void
    {
        $this->seed();

        $league = League::factory()->create();

        $league->settings()->create([
            'key' => LeagueSetting::BUDGET_RULES_MUTABLE,
            'value' => LeagueSetting::booleanPayload(true),
        ]);

        $migrationFiles = glob(
            database_path(
                'migrations/*_backfill_league_rule_mutability_settings.php'
            )
        );

        $this->assertCount(
            1,
            $migrationFiles,
            'Expected exactly one league rule mutability backfill migration.'
        );

        $migration = require $migrationFiles[0];

        $migration->up();
        $migration->up();

        $league->refresh();

        $this->assertTrue($league->budgetRulesMutable());

        foreach ([
            LeagueSetting::BUDGET_RULES_MUTABLE,
            LeagueSetting::ROSTER_SIZE_MUTABLE,
            LeagueSetting::ROSTER_ROLE_LIMITS_MUTABLE,
        ] as $key) {
            $this->assertSame(
                1,
                $league->settings()
                    ->where('key', $key)
                    ->count()
            );
        }

        $this->assertFalse($league->rosterSizeMutable());
        $this->assertFalse($league->rosterRoleLimitsMutable());
    }
}