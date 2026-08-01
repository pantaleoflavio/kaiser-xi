<?php

namespace Database\Seeders;

use Database\Seeders\DemoFantasyRostersSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\DemoPlayersSeeder;
use Database\Seeders\LeagueRoleSeeder;
use Database\Seeders\LeagueStatusSeeder;
use Database\Seeders\LeagueTypeSeeder;
use Database\Seeders\PlayerRoleSeeder;
use Database\Seeders\RealCompetitionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Seeder;

class DemoEnvironmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RealCompetitionSeeder::class,
            PlayerRoleSeeder::class,
            LeagueStatusSeeder::class,
            LeagueTypeSeeder::class,
            LeagueRoleSeeder::class,
            DemoLeagueSeeder::class,
            DemoPlayersSeeder::class,
            DemoFantasyRostersSeeder::class,
        ]);
    }
}
