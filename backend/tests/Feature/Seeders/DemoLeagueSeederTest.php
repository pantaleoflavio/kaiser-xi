<?php

namespace Tests\Feature\Seeders;

use App\Models\League;
use App\Models\LeagueSetting;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\RealCompetitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLeagueSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_demo_league_roster_settings_are_seeded_idempotently(): void
    {
        $this->seed(RealCompetitionSeeder::class);

        $this->seed(DemoLeagueSeeder::class);
        $this->seed(DemoLeagueSeeder::class);

        $league = League::query()
            ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
            ->firstOrFail();

        $this->assertSame(
            1,
            $league->settings()
                ->where('key', LeagueSetting::MAX_ROSTER_PLAYERS)
                ->count()
        );

        $this->assertSame(
            1,
            $league->settings()
                ->where('key', LeagueSetting::ROSTER_ROLE_LIMITS)
                ->count()
        );

        $this->assertSame(
            1,
            $league->settings()
                ->where('key', LeagueSetting::REAL_CAPTAIN_BONUS_POINTS)
                ->count()
        );

        $this->assertSame(
            1,
            $league->settings()
                ->where('key', LeagueSetting::DEFENSE_MODIFIER_ENABLED)
                ->count()
        );
    }
}
