<?php

namespace Tests\Feature\Seeders;

use App\Models\Formation;
use App\Models\League;
use App\Models\Player;
use Database\Seeders\DemoEnvironmentSeeder;
use Database\Seeders\DemoExtendedPlayerPoolSeeder;
use Database\Seeders\DemoFormulaOneChampionshipSeeder;
use Database\Seeders\DemoHeadToHeadLeagueSeeder;
use Database\Seeders\DemoHeadToHeadResultsSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('demo-integration')]
class DemoEnvironmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_environment_is_complete(): void
    {
        $this->seed(DemoEnvironmentSeeder::class);

        $this->assertSame(
            1,
            League::query()
                ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
                ->count(),
        );

        $this->assertSame(
            1,
            League::query()
                ->where('slug', DemoHeadToHeadLeagueSeeder::LEAGUE_SLUG)
                ->count(),
        );

        $this->assertSame(
            1,
            League::query()
                ->where('slug', DemoHeadToHeadResultsSeeder::LEAGUE_SLUG)
                ->count(),
        );

        $formulaOne = League::query()
            ->where('slug', DemoFormulaOneChampionshipSeeder::LEAGUE_SLUG)
            ->firstOrFail();

        $this->assertSame(6, $formulaOne->fantasyTeams()->count());
        $this->assertTrue($formulaOne->hasInitializedChampionship());
        $this->assertSame(
            DemoFormulaOneChampionshipSeeder::MATCHDAY_COUNT,
            $formulaOne->season->matchdays()->count(),
        );
        $this->assertSame(6, $formulaOne->standings()->count());

        $historicalFormations = Formation::query()
            ->whereHas('matchday', fn($query) => $query->where('ends_at', '<', now()))
            ->whereHas('league', fn($query) => $query->whereIn('slug', [
                DemoLeagueSeeder::LEAGUE_SLUG,
                DemoHeadToHeadResultsSeeder::LEAGUE_SLUG,
                DemoFormulaOneChampionshipSeeder::LEAGUE_SLUG,
            ]));

        $this->assertGreaterThan(0, $historicalFormations->count());
        $this->assertSame(0, (clone $historicalFormations)->whereDoesntHave('players')->count());
        $this->assertSame(0, Formation::query()
            ->whereHas('league', fn($query) => $query->whereIn('slug', [
                DemoLeagueSeeder::LEAGUE_SLUG,
                DemoHeadToHeadResultsSeeder::LEAGUE_SLUG,
                DemoFormulaOneChampionshipSeeder::LEAGUE_SLUG,
            ]))
            ->where(function ($query): void {
                $query->whereDoesntHave('fantasyTeam')
                    ->orWhereDoesntHave('matchday')
                    ->orWhereDoesntHave('formationModule');
            })
            ->count());

        $this->assertSame(
            count(DemoExtendedPlayerPoolSeeder::FREE_AGENTS),
            Player::query()
                ->whereIn(
                    'slug',
                    collect(DemoExtendedPlayerPoolSeeder::FREE_AGENTS)->pluck(1),
                )
                ->count(),
        );
    }
}
