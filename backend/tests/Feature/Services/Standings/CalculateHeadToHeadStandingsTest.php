<?php

namespace Tests\Feature\Services\Standings;

use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\Standing;
use App\Services\Standings\CalculateHeadToHeadStandings;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateHeadToHeadStandingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_calculates_the_table_from_calculated_results_only(): void
    {
        [$league, $teams] = $this->scheduledLeague(4);
        $this->createFantasyMatchResult($league, $teams[0], $teams[1], 2, 1);
        $this->createFantasyMatchResult($league, $teams[2], $teams[3], 1, 1);
        $this->createFantasyMatchResult($league, $teams[0], $teams[2], 0, 0);
        $this->createFantasyMatchResult($league, $teams[1], $teams[3], 8, 0, 'pending');

        $table = app(CalculateHeadToHeadStandings::class)->calculate($league)->keyBy('fantasy_team_id');

        $this->assertStatistics($table[$teams[0]->id], [2, 1, 1, 0, 2, 1, 4, 1]);
        $this->assertStatistics($table[$teams[1]->id], [1, 0, 0, 1, 1, 2, 0, -1]);
        $this->assertStatistics($table[$teams[2]->id], [2, 0, 2, 0, 1, 1, 2, 0]);
        $this->assertStatistics($table[$teams[3]->id], [1, 0, 1, 0, 1, 1, 1, 0]);
    }

    public function test_ranks_by_each_tie_breaker_and_then_team_id(): void
    {
        [$league, $teams] = $this->scheduledLeague(4);
        // All teams earn three points. A/B have GD +1, C has GD 0, D has GD -2.
        // A and B are separated by goals for; adding the same data in a second league covers ID fallback.
        $this->createFantasyMatchResult($league, $teams[0], $teams[2], 2, 1);
        $this->createFantasyMatchResult($league, $teams[1], $teams[3], 1, 0);
        $this->createFantasyMatchResult($league, $teams[2], $teams[3], 3, 1);

        $table = app(CalculateHeadToHeadStandings::class)->calculate($league);
        $this->assertSame([$teams[2]->id, $teams[0]->id, $teams[1]->id, $teams[3]->id], $table->pluck('fantasy_team_id')->all());

        [$goalDifferenceLeague, $goalDifferenceTeams] = $this->scheduledLeague(4);
        $this->createFantasyMatchResult($goalDifferenceLeague, $goalDifferenceTeams[0], $goalDifferenceTeams[1], 3, 1);
        $this->createFantasyMatchResult($goalDifferenceLeague, $goalDifferenceTeams[2], $goalDifferenceTeams[3], 4, 3);
        $goalDifferenceTable = app(CalculateHeadToHeadStandings::class)->calculate($goalDifferenceLeague);
        $this->assertSame($goalDifferenceTeams[0]->id, $goalDifferenceTable->first()->fantasy_team_id);

        [$tiedLeague, $tiedTeams] = $this->scheduledLeague(2);
        $tied = app(CalculateHeadToHeadStandings::class)->calculate($tiedLeague);
        $this->assertSame($tiedTeams->pluck('id')->sort()->values()->all(), $tied->pluck('fantasy_team_id')->all());
        $this->assertSame([1, 2], $tied->pluck('position')->all());
    }

    public function test_generated_schedule_with_no_results_includes_every_participant_at_zero(): void
    {
        [$league, $teams] = $this->scheduledLeague(4);

        $table = app(CalculateHeadToHeadStandings::class)->calculate($league);

        $this->assertSame($teams->pluck('id')->sort()->values()->all(), $table->pluck('fantasy_team_id')->all());
        foreach ($table as $standing) {
            $this->assertStatistics($standing, [0, 0, 0, 0, 0, 0, 0, 0]);
        }
    }

    public function test_recalculation_reuses_rows_and_replaces_counters(): void
    {
        [$league, $teams] = $this->scheduledLeague(2);
        $result = $this->createFantasyMatchResult($league, $teams[0], $teams[1], 2, 1);
        $calculator = app(CalculateHeadToHeadStandings::class);
        $firstIds = $calculator->calculate($league)->pluck('id')->all();

        $result->update(['home_goals' => 0, 'away_goals' => 3]);
        $second = $calculator->calculate($league);
        $third = $calculator->calculate($league);

        $this->assertSame($firstIds, $second->pluck('id')->all());
        $this->assertSame($second->toArray(), $third->toArray());
        $this->assertSame(2, Standing::query()->where('league_id', $league->id)->count());
        $this->assertSame($teams[1]->id, $second->first()->fantasy_team_id);
        $this->assertStatistics($second->first(), [1, 1, 0, 0, 3, 0, 3, 3]);
    }

    public function test_removed_participant_and_results_from_later_schedule_rounds_remain_counted(): void
    {
        [$league, $teams] = $this->scheduledLeague(4);
        $league->memberships()->where('user_id', $teams[0]->user_id)->delete();
        $this->createFantasyMatchResult($league, $teams[0], $teams[1], 2, 0);
        $this->createFantasyMatchResult($league, $teams[2], $teams[0], 1, 0); // another persisted schedule round

        $table = app(CalculateHeadToHeadStandings::class)->calculate($league)->keyBy('fantasy_team_id');

        $this->assertCount(4, $table);
        $this->assertStatistics($table[$teams[0]->id], [2, 1, 0, 1, 2, 1, 3, 1]);
    }

    public function test_rejects_uninitialized_and_non_head_to_head_leagues(): void
    {
        $service = app(CalculateHeadToHeadStandings::class);
        foreach (['classic', 'formula_one'] as $key) {
            $league = League::factory()->create([
                'league_type_id' => LeagueType::query()->where('key', $key)->firstOrFail()->id,
            ]);
            try {
                $service->calculate($league);
                $this->fail("{$key} must be rejected.");
            } catch (DomainException) {
                $this->assertDatabaseMissing('standings', ['league_id' => $league->id]);
            }
        }

        $headToHead = League::factory()->create([
            'league_type_id' => LeagueType::query()->where('key', 'head_to_head')->firstOrFail()->id,
        ]);
        $this->expectException(DomainException::class);
        $service->calculate($headToHead);
    }

    /** @return array{League, \Illuminate\Database\Eloquent\Collection<int, FantasyTeam>} */
    private function scheduledLeague(int $teamCount): array
    {
        $type = LeagueType::query()->where('key', 'head_to_head')->firstOrFail();
        $league = League::factory()->create(['league_type_id' => $type->id, 'h2h_schedule_generated_at' => now()]);
        $teams = FantasyTeam::factory()->count($teamCount)->create(['league_id' => $league->id]);
        $roleId = \App\Models\LeagueRole::query()->where('key', 'participant')->value('id');
        foreach ($teams as $team) {
            $league->users()->attach($team->user_id, ['league_role_id' => $roleId, 'joined_at' => now()]);
        }

        $matchday = Matchday::factory()->create(['season_id' => $league->season_id, 'number' => 1]);
        for ($index = 0; $index < $teamCount; $index += 2) {
            FantasyMatch::factory()->create([
                'league_id' => $league->id,
                'matchday_id' => $matchday->id,
                'home_fantasy_team_id' => $teams[$index]->id,
                'away_fantasy_team_id' => $teams[$index + 1]->id,
            ]);
        }

        return [$league, $teams];
    }

    private function createFantasyMatchResult(League $league, FantasyTeam $home, FantasyTeam $away, int $homeGoals, int $awayGoals, string $status = 'calculated'): FantasyMatchResult
    {
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => Matchday::query()->where('season_id', $league->season_id)->max('number') + 1,
        ]);
        $match = FantasyMatch::factory()->create([
            'league_id' => $league->id,
            'matchday_id' => $matchday->id,
            'home_fantasy_team_id' => $home->id,
            'away_fantasy_team_id' => $away->id,
        ]);

        return FantasyMatchResult::factory()->create([
            'fantasy_match_id' => $match->id,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
            'result_status' => $status,
            'calculated_at' => $status === 'calculated' ? now() : null,
        ]);
    }

    /** @param array{int, int, int, int, int, int, int, int} $expected */
    private function assertStatistics(Standing $standing, array $expected): void
    {
        $this->assertSame($expected, [
            $standing->played,
            $standing->wins,
            $standing->draws,
            $standing->losses,
            $standing->goals_for,
            $standing->goals_against,
            $standing->points_total,
            $standing->goals_for - $standing->goals_against,
        ]);
    }
}
