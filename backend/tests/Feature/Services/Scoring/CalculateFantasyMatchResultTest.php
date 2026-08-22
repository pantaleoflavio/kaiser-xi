<?php

namespace Tests\Feature\Services\Scoring;

use App\Exceptions\IncompleteFantasyMatchScoreException;
use App\Models\FantasyMatch;
use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use App\Services\Scoring\CalculateFantasyMatchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateFantasyMatchResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_calculates_and_idempotently_updates_a_result(): void
    {
        [$match, $homeScore, $awayScore] = $this->fixture(77, 69.5);
        $calculator = app(CalculateFantasyMatchResult::class);

        $first = $calculator->calculate($match);
        $this->assertSame([2, 1], [$first->home_goals, $first->away_goals]);
        $this->assertSame(['77.00', '69.50'], [$first->home_points, $first->away_points]);

        $homeScore->update(['points' => 84]);
        $second = $calculator->calculate($match);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(4, $second->home_goals);
        $this->assertSame('calculated', $second->result_status);
        $this->assertSame(1, $match->result()->count());
    }

    public function test_missing_active_score_fails_without_overwriting_existing_result(): void
    {
        [$match,, $awayScore] = $this->fixture(72, 75);
        $calculator = app(CalculateFantasyMatchResult::class);
        $existing = $calculator->calculate($match);
        $awayScore->delete();

        try {
            $calculator->calculate($match);
            $this->fail('Calculation should require an active team score.');
        } catch (IncompleteFantasyMatchScoreException) {
            $this->assertSame('75.00', $existing->fresh()->away_points);
        }
    }

    public function test_removed_participant_scores_zero_without_a_score_row(): void
    {
        [$match, $homeScore] = $this->fixture(77, 69.5);
        $homeScore->delete();
        $match->league->memberships()->where('user_id', $match->homeFantasyTeam->user_id)->delete();

        $result = app(CalculateFantasyMatchResult::class)->calculate($match);
        $this->assertSame('0.00', $result->home_points);
        $this->assertSame(0, $result->home_goals);
        $this->assertSame(1, $result->away_goals);
    }

    public function test_settings_update_does_not_recalculate_a_result_and_explicit_recalculation_uses_current_goal_rules(): void
    {
        [$match] = $this->fixture(77, 69.5);

        $calculator = app(CalculateFantasyMatchResult::class);

        $historical = $calculator->calculate($match);

        $this->assertSame(
            [2, 1],
            [$historical->home_goals, $historical->away_goals]
        );

        app(LeagueSettingsService::class)->update($match->league, [
            LeagueSetting::FIRST_GOAL_THRESHOLD => 68,
            LeagueSetting::GOAL_INTERVAL => 7,
        ]);

        // Updating the settings must not mutate an already persisted result.
        $this->assertSame(
            [2, 1],
            [
                $historical->fresh()->home_goals,
                $historical->fresh()->away_goals,
            ]
        );

        // Explicit recalculation uses the current rules.
        $recalculated = $calculator->calculate($match->fresh());

        $this->assertSame(
            [2, 1],
            [$recalculated->home_goals, $recalculated->away_goals]
        );

        app(LeagueSettingsService::class)->update(
            $match->league->refresh(),
            [
                LeagueSetting::FIRST_GOAL_THRESHOLD => 78,
                LeagueSetting::GOAL_INTERVAL => 10,
            ]
        );

        // Changing the rules still does not automatically mutate
        // the previously calculated result.
        $this->assertSame(
            [2, 1],
            [
                $recalculated->fresh()->home_goals,
                $recalculated->fresh()->away_goals,
            ]
        );

        // A new explicit recalculation uses the latest rules.
        $usingLatestRules = $calculator->calculate($match->fresh());

        $this->assertSame(
            [0, 0],
            [$usingLatestRules->home_goals, $usingLatestRules->away_goals]
        );
    }

    /** @return array{FantasyMatch, TeamMatchdayScore, TeamMatchdayScore} */
    private function fixture(float $homePoints, float $awayPoints): array
    {
        $type = LeagueType::query()->where('key', 'head_to_head')->firstOrFail();
        $league = League::factory()->create(['league_type_id' => $type->id]);
        $matchday = Matchday::factory()->create(['season_id' => $league->season_id]);
        $teams = collect([User::factory()->create(), User::factory()->create()])->map(function (User $user) use ($league): FantasyTeam {
            $league->users()->attach($user, ['league_role_id' => LeagueRole::query()->where('key', 'participant')->value('id'), 'joined_at' => now()]);
            return FantasyTeam::factory()->forLeagueAndUser($league, $user)->create();
        });
        $match = FantasyMatch::factory()->create(['league_id' => $league->id, 'matchday_id' => $matchday->id, 'home_fantasy_team_id' => $teams[0]->id, 'away_fantasy_team_id' => $teams[1]->id]);
        $scores = $teams->map(fn(FantasyTeam $team, int $index): TeamMatchdayScore => TeamMatchdayScore::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
            'formation_id' => Formation::factory()->create(['league_id' => $league->id, 'fantasy_team_id' => $team->id, 'matchday_id' => $matchday->id])->id,
            'points' => $index === 0 ? $homePoints : $awayPoints,
            'status' => 'calculated',
            'calculated_at' => now(),
        ]));

        return [$match, $scores[0], $scores[1]];
    }
}
