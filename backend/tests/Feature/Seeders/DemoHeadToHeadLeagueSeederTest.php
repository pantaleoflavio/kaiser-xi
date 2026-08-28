<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyMatch;
use App\Models\League;
use Database\Seeders\DemoHeadToHeadLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;

#[Group('demo-integration')]
class DemoHeadToHeadLeagueSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoFoundation;

    public function test_schedule_lab_is_uninitialized_and_independently_idempotent(): void
    {
        $this->seedDemoFoundation(false);
        $this->seed(DemoHeadToHeadLeagueSeeder::class);
        $this->seed(DemoHeadToHeadLeagueSeeder::class);

        $league = League::query()->with('type')->where('slug', DemoHeadToHeadLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $teamCount = count(DemoHeadToHeadLeagueSeeder::PARTICIPANTS);

        $this->assertSame(1, League::query()->where('slug', DemoHeadToHeadLeagueSeeder::LEAGUE_SLUG)->count());
        $this->assertSame('head_to_head', $league->type->key);
        $this->assertSame($teamCount, $league->memberships()->count());
        $this->assertSame($teamCount, $league->fantasyTeams()->count());
        $this->assertNull($league->h2h_start_matchday_id);
        $this->assertNull($league->h2h_schedule_generated_at);
        $this->assertSame(0, FantasyMatch::query()->whereBelongsTo($league)->count());
        $this->assertSame(DemoHeadToHeadLeagueSeeder::FUTURE_MATCHDAY_COUNT, $league->season->matchdays()
            ->whereBetween('number', [100, 111])->count());
    }
}
