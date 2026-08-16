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
