<?php

namespace Database\Seeders;

use Database\Seeders\DemoFantasyRostersSeeder;
use Database\Seeders\DemoHeadToHeadLeagueSeeder;
use Database\Seeders\DemoHeadToHeadResultsSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\DemoMatchdaySeeder;
use Database\Seeders\DemoPlayersSeeder;
use Database\Seeders\FormationModuleSeeder;
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
            FormationModuleSeeder::class,
            DemoLeagueSeeder::class,
            DemoPlayersSeeder::class,
            DemoExtendedPlayerPoolSeeder::class,
            DemoFantasyRostersSeeder::class,
            // The results arena owns an isolated Season but still depends on the shared player pool.
            DemoHeadToHeadResultsSeeder::class,
            DemoMatchdaySeeder::class,
            DemoHeadToHeadLeagueSeeder::class,
            DemoClassicChampionshipSeeder::class,
        ]);
    }
}
