<?php

namespace Database\Seeders;

use App\Models\RealClub;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Seeder;

class DemoSeasonSeeder extends Seeder
{
    public const SEASON_NAME = '2025/2026';

    public const CLUBS = [
        ['Aurora FC', 'Aurora', 'demo-aurora-fc'],
        ['Borealis United', 'Borealis', 'demo-borealis-united'],
        ['Calcio Marina', 'Marina', 'demo-calcio-marina'],
        ['Dolomiti Athletic', 'Dolomiti', 'demo-dolomiti-athletic'],
    ];

    public function run(): void
    {
        $competition = RealCompetition::query()->where('code', 'serie_a')->firstOrFail();
        $season = Season::query()->updateOrCreate(
            ['real_competition_id' => $competition->id, 'name' => self::SEASON_NAME],
            [
                'starts_at' => '2025-08-01',
                'ends_at' => '2026-05-31',
                'is_active' => true,
            ],
        );

        foreach (self::CLUBS as [$name, $shortName, $slug]) {
            $club = RealClub::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'short_name' => $shortName, 'country_code' => 'IT', 'logo_path' => null],
            );

            SeasonClub::query()->updateOrCreate(
                ['season_id' => $season->id, 'real_club_id' => $club->id],
                ['display_name' => $name, 'is_active' => true],
            );
        }
    }
}
