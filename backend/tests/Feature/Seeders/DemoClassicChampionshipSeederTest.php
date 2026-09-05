<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use Database\Seeders\DemoClassicChampionshipSeeder;
use Database\Seeders\DemoFantasyRostersSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('demo-integration')]
class DemoClassicChampionshipSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoFoundation;

    public function test_classic_championship_scenario_is_complete_and_independently_idempotent(): void
    {
        $this->seedDemoFoundation();
        $this->seed(DemoFantasyRostersSeeder::class);
        $this->seed(DemoClassicChampionshipSeeder::class);

        $league = League::query()->with(['type', 'classicStartMatchday'])->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $teams = $league->fantasyTeams()->orderBy('id')->get();
        $matchdays = Matchday::query()->where('season_id', $league->season_id)
            ->whereBetween('number', [200, 207])->orderBy('number')->get();
        $past = $matchdays->take(3);
        $current = $matchdays->firstWhere('number', 203);
        $missingTeam = $teams->firstWhere('slug', DemoClassicChampionshipSeeder::MISSING_FORMATION_TEAM_SLUG);
        $missingMatchday = $past->get(1);

        $this->assertSame('classic', $league->type->key);
        $this->assertSame(9, $teams->count());
        $this->assertTrue($league->hasInitializedClassicChampionship());
        $this->assertSame(200, $league->classicStartMatchday->number);
        $this->assertSame(9, $league->classicParticipants()->count());
        $this->assertSame(8, $matchdays->count());

        foreach ($teams as $team) {
            $counts = $this->activeRoleCounts($league, $team);
            $this->assertGreaterThanOrEqual(2, $counts['goalkeeper']);
            $this->assertGreaterThanOrEqual(5, $counts['defender']);
            $this->assertGreaterThanOrEqual(5, $counts['midfielder']);
            $this->assertGreaterThanOrEqual(3, $counts['forward']);
        }

        $expectedSubmitted = (9 * 3) - 1;
        $this->assertSame($expectedSubmitted, Formation::query()->whereBelongsTo($league)
            ->whereIn('matchday_id', $past->pluck('id'))->whereNotNull('submitted_at')->count());
        $this->assertFalse(Formation::query()->whereBelongsTo($league)->whereBelongsTo($missingMatchday)
            ->whereBelongsTo($missingTeam)->exists());
        $this->assertSame($expectedSubmitted, TeamMatchdayScore::query()->whereBelongsTo($league)
            ->whereIn('matchday_id', $past->pluck('id'))->count());
        $this->assertFalse(TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($missingMatchday)
            ->whereBelongsTo($missingTeam)->exists());

        $missing = Standing::query()->whereBelongsTo($league)->whereBelongsTo($missingTeam)->firstOrFail();
        $this->assertSame(3, $missing->played);
        $this->assertSame('148.50', $missing->fantasy_points_total);
        $this->assertSame('49.5000', $missing->average_points);
        $this->assertSame('82.50', $missing->best_matchday_score);
        $leader = Standing::query()->whereBelongsTo($league)->where('position', 1)->firstOrFail();
        $this->assertSame('commissioner-fc', $leader->fantasyTeam->slug);
        $this->assertSame('231.50', $leader->fantasy_points_total);

        $this->assertSame(1, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)
            ->whereNotNull('submitted_at')->count());
        $this->assertSame(1, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)
            ->whereNull('submitted_at')->count());
        $this->assertSame(0, TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($current)->count());
        $this->assertSame(0, Formation::query()->whereBelongsTo($league)
            ->whereIn('matchday_id', $matchdays->skip(4)->pluck('id'))->count());

        $counts = $this->scenarioCounts($league, $past->pluck('id')->all());
        $this->seed(DemoClassicChampionshipSeeder::class);
        $this->assertSame($counts, $this->scenarioCounts($league, $past->pluck('id')->all()));
    }

    /** @return array<string, int> */
    private function activeRoleCounts(League $league, FantasyTeam $team): array
    {
        return FantasyTeamPlayer::query()->active()->whereBelongsTo($league)->whereBelongsTo($team)
            ->join('players', 'players.id', '=', 'fantasy_team_players.player_id')
            ->join('player_season_registrations', 'player_season_registrations.player_id', '=', 'players.id')
            ->join('player_roles', 'player_roles.id', '=', 'player_season_registrations.player_role_id')
            ->selectRaw('player_roles.key, count(*) as aggregate')->groupBy('player_roles.key')
            ->pluck('aggregate', 'player_roles.key')->map(fn($count): int => (int) $count)->all();
    }

    /** @param list<int> $pastMatchdayIds @return array<string, int> */
    private function scenarioCounts(League $league, array $pastMatchdayIds): array
    {
        $formationIds = Formation::query()->whereBelongsTo($league)->pluck('id');

        return [
            'assignments' => FantasyTeamPlayer::query()->whereBelongsTo($league)->count(),
            'formations' => $formationIds->count(),
            'formation_players' => FormationPlayer::query()->whereIn('formation_id', $formationIds)->count(),
            'player_scores' => PlayerScore::query()->whereIn('matchday_id', $pastMatchdayIds)->count(),
            'team_scores' => TeamMatchdayScore::query()->whereBelongsTo($league)->count(),
            'standings' => Standing::query()->whereBelongsTo($league)->count(),
        ];
    }
}
