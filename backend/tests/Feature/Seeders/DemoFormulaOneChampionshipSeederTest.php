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
use Database\Seeders\DemoFormulaOneChampionshipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;

#[Group('demo-integration')]
class DemoFormulaOneChampionshipSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoFoundation;

    public function test_formula_one_scenario_is_complete_and_independently_idempotent(): void
    {
        $this->seedDemoFoundation();
        $this->seed(DemoFormulaOneChampionshipSeeder::class);

        $league = League::query()->with(['type', 'championshipStartMatchday'])->where('slug', DemoFormulaOneChampionshipSeeder::LEAGUE_SLUG)->firstOrFail();
        $teams = $league->fantasyTeams()->orderBy('id')->get();
        $matchdays = Matchday::query()->where('season_id', $league->season_id)->orderBy('number')->get();
        $past = $matchdays->take(DemoFormulaOneChampionshipSeeder::PAST_MATCHDAY_COUNT);
        $current = $matchdays->firstWhere('number', DemoFormulaOneChampionshipSeeder::CURRENT_MATCHDAY_NUMBER);
        $missingTeam = $teams->get(DemoFormulaOneChampionshipSeeder::MISSING_FORMATION_TEAM_INDEX);

        $this->assertSame('formula_one', $league->type->key);
        $this->assertSame(6, $teams->count());
        $this->assertTrue($league->hasInitializedChampionship());
        $this->assertSame(DemoFormulaOneChampionshipSeeder::FIRST_MATCHDAY_NUMBER, $league->championshipStartMatchday->number);
        $this->assertSame(6, $league->championshipParticipants()->count());
        $this->assertSame(8, $matchdays->count());
        $this->assertSame([25, 18, 15, 12, 10, 8, 6, 4, 2, 1], array_values($league->formulaOnePositionPoints()));

        foreach ($teams as $team) {
            $this->assertSame(
                ['defender' => 5, 'forward' => 3, 'goalkeeper' => 2, 'midfielder' => 5],
                $this->activeRoleCounts($league, $team),
            );
        }

        $this->assertFalse(Formation::query()->whereBelongsTo($league)->whereBelongsTo($past->get(1))->whereBelongsTo($missingTeam)->exists());
        $this->assertFalse(TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($past->get(1))->whereBelongsTo($missingTeam)->exists());
        $this->assertSame(17, TeamMatchdayScore::query()->whereBelongsTo($league)->whereIn('matchday_id', $past->pluck('id'))->count());

        foreach (DemoFormulaOneChampionshipSeeder::SCORE_MATRIX as $matchdayIndex => $scores) {
            foreach ($scores as $teamIndex => $expected) {
                $score = TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($past->get($matchdayIndex))
                    ->whereBelongsTo($teams->get($teamIndex))->value('points');
                $this->assertSame($matchdayIndex === 1 && $teamIndex === 5 ? null : number_format($expected, 2, '.', ''), $score);
            }
        }

        $expectedStandings = [
            1 => [$teams->get(1)->id, 3, 58, 1, 3, 1, '234.00', '78.0000'],
            2 => [$teams->get(0)->id, 3, 58, 1, 3, 1, '232.00', '77.3333'],
            3 => [$teams->get(2)->id, 3, 58, 1, 3, 1, '231.00', '77.0000'],
            4 => [$teams->get(3)->id, 3, 36, 0, 0, 4, '213.00', '71.0000'],
            5 => [$teams->get(4)->id, 3, 28, 0, 0, 5, '202.00', '67.3333'],
            6 => [$teams->get(5)->id, 3, 26, 0, 0, 5, '133.00', '44.3333'],
        ];
        foreach ($expectedStandings as $position => $expected) {
            $standing = Standing::query()->whereBelongsTo($league)->where('position', $position)->firstOrFail();
            $this->assertSame($expected, [
                $standing->fantasy_team_id,
                $standing->played,
                $standing->championship_points,
                $standing->wins,
                $standing->podiums,
                $standing->best_finish,
                $standing->fantasy_points_total,
                $standing->average_points,
            ]);
        }

        $placements = app(\App\Services\Standings\CalculateFormulaOneStandings::class)
            ->placementsFor($league, $past->get(2)->id)->keyBy('fantasyTeamId');
        $this->assertLessThan($teams->get(1)->id, $teams->get(0)->id);
        $this->assertSame(2, $placements[$teams->get(0)->id]->position);
        $this->assertSame(18, $placements[$teams->get(0)->id]->championshipPoints);
        $this->assertSame(3, $placements[$teams->get(1)->id]->position);
        $this->assertSame(15, $placements[$teams->get(1)->id]->championshipPoints);

        $this->assertSame(1, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)->whereNotNull('submitted_at')->count());
        $this->assertSame(1, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)->whereNull('submitted_at')->count());
        $this->assertSame(0, TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($current)->count());
        $this->assertSame(0, Formation::query()->whereBelongsTo($league)->whereIn('matchday_id', $matchdays->skip(4)->pluck('id'))->count());

        $counts = $this->scenarioCounts($league, $past->pluck('id')->all());
        $this->seed(DemoFormulaOneChampionshipSeeder::class);
        $this->assertSame($counts, $this->scenarioCounts($league, $past->pluck('id')->all()));
    }

    /** @return array<string, int> */
    private function activeRoleCounts(League $league, FantasyTeam $team): array
    {
        return FantasyTeamPlayer::query()->active()->whereBelongsTo($league)->whereBelongsTo($team)
            ->join('players', 'players.id', '=', 'fantasy_team_players.player_id')
            ->join('player_season_registrations', 'player_season_registrations.player_id', '=', 'players.id')
            ->join('season_clubs', function ($join) use ($league): void {
                $join->on('season_clubs.id', '=', 'player_season_registrations.season_club_id')->where('season_clubs.season_id', $league->season_id);
            })
            ->join('player_roles', 'player_roles.id', '=', 'player_season_registrations.player_role_id')
            ->selectRaw('player_roles.key, count(*) as aggregate')->groupBy('player_roles.key')->orderBy('player_roles.key')
            ->pluck('aggregate', 'player_roles.key')->map(fn($count): int => (int) $count)->all();
    }

    /** @param list<int> $pastMatchdayIds @return array<string, int> */
    private function scenarioCounts(League $league, array $pastMatchdayIds): array
    {
        $formationIds = Formation::query()->whereBelongsTo($league)->pluck('id');

        return [
            'memberships' => $league->memberships()->count(),
            'teams' => $league->fantasyTeams()->count(),
            'participants' => $league->championshipParticipants()->count(),
            'assignments' => FantasyTeamPlayer::query()->whereBelongsTo($league)->count(),
            'formations' => $formationIds->count(),
            'formation_players' => FormationPlayer::query()->whereIn('formation_id', $formationIds)->count(),
            'player_scores' => PlayerScore::query()->whereIn('matchday_id', $pastMatchdayIds)->count(),
            'team_scores' => TeamMatchdayScore::query()->whereBelongsTo($league)->count(),
            'standings' => Standing::query()->whereBelongsTo($league)->count(),
        ];
    }
}
