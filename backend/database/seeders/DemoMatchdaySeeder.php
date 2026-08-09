<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\Matchday;
use Illuminate\Database\Seeder;

class DemoMatchdaySeeder extends Seeder
{
    public const MATCHDAY_NUMBER = 99;

    public const MATCHDAY_NAME = 'Demo Formation Matchday';

    public function run(): void
    {
        $league = League::query()
            ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
            ->firstOrFail();

        Matchday::query()->updateOrCreate(
            [
                'season_id' => $league->season_id,
                'number' => self::MATCHDAY_NUMBER,
            ],
            [
                'name' => self::MATCHDAY_NAME,
                'starts_at' => '2099-08-01 18:00:00',
                'ends_at' => '2099-08-03 23:59:59',
            ],
        );
    }
}
