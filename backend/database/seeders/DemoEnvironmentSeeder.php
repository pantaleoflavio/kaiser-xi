<?php

namespace Database\Seeders;

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
