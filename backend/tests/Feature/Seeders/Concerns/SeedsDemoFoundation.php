<?php

namespace Tests\Feature\Seeders\Concerns;

use Database\Seeders\DemoExtendedPlayerPoolSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\DemoPlayersSeeder;
use Database\Seeders\FormationModuleSeeder;
use Database\Seeders\LeagueRoleSeeder;
use Database\Seeders\LeagueStatusSeeder;
use Database\Seeders\LeagueTypeSeeder;
use Database\Seeders\PlayerRoleSeeder;
use Database\Seeders\RealCompetitionSeeder;
use Database\Seeders\RoleSeeder;

trait SeedsDemoFoundation
{
    protected function seedDemoFoundation(bool $withPlayers = true): void
    {
        $seeders = [
            RoleSeeder::class,
            RealCompetitionSeeder::class,
            PlayerRoleSeeder::class,
            LeagueStatusSeeder::class,
            LeagueTypeSeeder::class,
            LeagueRoleSeeder::class,
            FormationModuleSeeder::class,
            DemoLeagueSeeder::class,
        ];

        if ($withPlayers) {
            $seeders[] = DemoPlayersSeeder::class;
            $seeders[] = DemoExtendedPlayerPoolSeeder::class;
        }

        $this->seed($seeders);
    }
}
