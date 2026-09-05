<?php

namespace Tests;

use Database\Seeders\FormationModuleSeeder;
use Database\Seeders\LeagueRoleSeeder;
use Database\Seeders\LeagueStatusSeeder;
use Database\Seeders\LeagueTypeSeeder;
use Database\Seeders\PlayerRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    protected function setUp(): void
    {
        // Laravel initializes database traits inside parent::setUp(). Bootstrap and
        // inspect the real application first, before RefreshDatabase can migrate it.
        if (! $this->app) {
            $this->refreshApplication();
        }

        TestDatabaseSafety::assertSafe($this->app);

        parent::setUp();
    }

    /**
     * Seed only the immutable lookup data commonly required by feature fixtures.
     *
     * Keep this list deliberately small: demo users, competitions, seasons, leagues,
     * rosters, scores, and standings belong in the tests that explicitly need them.
     */
    protected function seedReferenceData(): void
    {
        $this->seed([
            RoleSeeder::class,
            PlayerRoleSeeder::class,
            LeagueStatusSeeder::class,
            LeagueTypeSeeder::class,
            LeagueRoleSeeder::class,
            FormationModuleSeeder::class,
        ]);
    }
}
