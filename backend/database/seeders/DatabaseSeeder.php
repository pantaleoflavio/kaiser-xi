<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuperAdminSeeder::class,
            GlobalAdminSeeder::class,
            RealCompetitionSeeder::class,
            PlayerRoleSeeder::class,
            LeagueStatusSeeder::class,
            LeagueTypeSeeder::class,
            LeagueRoleSeeder::class,
            FormationModuleSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoEnvironmentSeeder::class);
        }
    }
}
