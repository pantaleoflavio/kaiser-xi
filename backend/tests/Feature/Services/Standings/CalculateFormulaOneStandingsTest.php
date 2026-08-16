<?php

namespace Tests\Feature\Services\Standings;

use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Services\Standings\CalculateFormulaOneStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateFormulaOneStandingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_all_frozen_teams_with_id_ties_missing_zero_custom_points_and_idempotent_recalculation(): void
    {
        $type = LeagueType::query()->create(['key' => 'formula_one', 'label' => 'Formula One']);
        $league = League::factory()->create(['league_type_id' => $type->id]);
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 4,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $teams = FantasyTeam::factory()->count(4)->create(['league_id' => $league->id])->sortBy('id')->values();
        $league->championshipParticipants()->attach($teams->pluck('id'));
        $league->update(['championship_start_matchday_id' => $matchday->id, 'championship_started_at' => now()]);
        $league->settings()->create([
            'key' => LeagueSetting::FORMULA_ONE_POSITION_POINTS,
            'value' => LeagueSetting::positionPointsPayload([1 => 10, 2 => 5, 3 => 2]),
        ]);
        $this->score($league, $teams[0], $matchday, 72);
        $this->score($league, $teams[1], $matchday, 72);
        $this->score($league, $teams[2], $matchday, 50);

        $service = app(CalculateFormulaOneStandings::class);
        $rows = $service->calculate($league);

        $this->assertSame($teams->pluck('id')->all(), $rows->pluck('fantasy_team_id')->all());
        $this->assertSame([10, 5, 2, 0], $rows->pluck('championship_points')->all());
        $this->assertSame(['72.00', '72.00', '50.00', '0.00'], $rows->pluck('fantasy_points_total')->all());
        $this->assertSame(4, $league->standings()->count());

        $league->standings()->where('fantasy_team_id', $teams[0]->id)->firstOrFail();
        TeamMatchdayScore::query()->where('fantasy_team_id', $teams[0]->id)->update(['points' => 40]);
        $recalculated = $service->calculate($league);
        $this->assertSame($teams[1]->id, $recalculated->first()->fantasy_team_id);
        $this->assertSame(4, $league->standings()->count());
    }

    public function test_default_position_points_are_formula_one_values(): void
    {
        $this->assertSame([1 => 25, 2 => 18, 3 => 15, 4 => 12, 5 => 10, 6 => 8, 7 => 6, 8 => 4, 9 => 2, 10 => 1], LeagueSetting::DEFAULT_FORMULA_ONE_POSITION_POINTS);
    }

    private function score(League $league, FantasyTeam $team, Matchday $matchday, int $points): void
    {
        $formation = Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
        ]);
        TeamMatchdayScore::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
            'formation_id' => $formation->id,
            'points' => $points,
        ]);
    }
}
