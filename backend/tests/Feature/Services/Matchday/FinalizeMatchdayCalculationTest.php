<?php

namespace Tests\Feature\Services\Matchday;

use App\Enums\PlayerScoreStatus;
use App\Http\Controllers\Api\V1\MatchdayController;
use App\Models\FantasyMatch;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\LeagueMatchdayCalculation;
use App\Models\LeagueRole;
use App\Models\LeagueSetting;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use App\Services\Matchday\FinalizeMatchday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FinalizeMatchdayCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_classic_recalculation_replaces_derived_data_and_preserves_source_rows(): void
    {
        [$league, $matchday] = $this->championship('classic');
        [$team, $formation, $formationPlayer, $playerScore, $assignment] = $this->submittedTeam($league, $matchday, 70);
        $league->championshipParticipants()->attach($team);

        $this->finalizer()->calculate($league, $matchday);

        $scoreId = TeamMatchdayScore::query()->sole()->id;
        $detailId = TeamMatchdayScoreDetail::query()->sole()->id;
        $this->assertSame('70.00', TeamMatchdayScore::query()->sole()->points);
        $this->assertSame('70.00', Standing::query()->sole()->fantasy_points_total);
        $this->assertDatabaseCount('league_matchday_calculations', 1);

        $playerScore->update(['final_score' => 82]);
        $this->assertSame('70.00', TeamMatchdayScore::query()->sole()->points);
        $this->assertSame('70.00', Standing::query()->sole()->fantasy_points_total);

        $this->finalizer()->calculate($league, $matchday);

        $this->assertSame('82.00', TeamMatchdayScore::query()->sole()->points);
        $this->assertSame('82.00', Standing::query()->sole()->fantasy_points_total);
        $this->assertNotSame($scoreId, TeamMatchdayScore::query()->sole()->id);
        $this->assertNotSame($detailId, TeamMatchdayScoreDetail::query()->sole()->id);
        $this->assertDatabaseCount('team_matchday_scores', 1);
        $this->assertDatabaseCount('team_matchday_score_details', 1);
        $this->assertDatabaseCount('standings', 1);
        $this->assertDatabaseCount('league_matchday_calculations', 1);
        $this->assertSourceRowsRemain($league, $matchday, $team, $formation, $formationPlayer, $playerScore, $assignment);
    }

    public function test_head_to_head_recalculation_uses_current_goal_rules_and_rebuilds_standings(): void
    {
        $season = Season::factory()->create();
        $matchday = $this->endedMatchday($season);
        $league = $this->league('head_to_head', $season, ['h2h_schedule_generated_at' => now()]);
        [$home, $homeFormation, $homePlayer, $homeScore, $homeAssignment] = $this->submittedTeam($league, $matchday, 78);
        [$away] = $this->submittedTeam($league, $matchday, 72);
        $match = FantasyMatch::factory()->create([
            'league_id' => $league->id,
            'matchday_id' => $matchday->id,
            'home_fantasy_team_id' => $home->id,
            'away_fantasy_team_id' => $away->id,
        ]);

        $this->finalizer()->calculate($league, $matchday);
        $this->assertSame([3, 2], [$match->result->home_goals, $match->result->away_goals]);
        $this->assertSame([3, 0], Standing::query()->where('league_id', $league->id)->orderBy('fantasy_team_id')->pluck('points_total')->all());

        $this->decimalSetting($league, LeagueSetting::FIRST_GOAL_THRESHOLD, 78);
        $this->decimalSetting($league, LeagueSetting::GOAL_INTERVAL, 10);
        $this->assertSame([3, 2], [$match->fresh()->result->home_goals, $match->fresh()->result->away_goals]);

        $this->finalizer()->calculate($league->fresh(), $matchday);

        $this->assertSame([1, 0], [$match->fresh()->result->home_goals, $match->fresh()->result->away_goals]);
        $this->assertSame([3, 0], Standing::query()->where('league_id', $league->id)->orderBy('fantasy_team_id')->pluck('points_total')->all());
        $this->assertDatabaseCount('fantasy_match_results', 1);
        $this->assertDatabaseCount('team_matchday_scores', 2);
        $this->assertDatabaseCount('team_matchday_score_details', 2);
        $this->assertSourceRowsRemain($league, $matchday, $home, $homeFormation, $homePlayer, $homeScore, $homeAssignment);
    }

    public function test_formula_one_recalculation_uses_current_position_points_without_changing_positions(): void
    {
        [$league, $matchday] = $this->championship('formula_one');
        [$winner, $formation, $formationPlayer, $playerScore, $assignment] = $this->submittedTeam($league, $matchday, 90);
        [$runnerUp] = $this->submittedTeam($league, $matchday, 80);
        $league->championshipParticipants()->attach([$winner->id, $runnerUp->id]);

        $this->finalizer()->calculate($league, $matchday);
        $this->assertSame([25, 18], Standing::query()->where('league_id', $league->id)->orderBy('position')->pluck('championship_points')->all());

        $league->settings()->updateOrCreate(
            ['key' => LeagueSetting::FORMULA_ONE_POSITION_POINTS],
            ['value' => LeagueSetting::positionPointsPayload([1 => 40, 2 => 20])],
        );
        $this->assertSame([25, 18], Standing::query()->where('league_id', $league->id)->orderBy('position')->pluck('championship_points')->all());

        $this->finalizer()->calculate($league->fresh(), $matchday);

        $standings = Standing::query()->where('league_id', $league->id)->orderBy('position')->get();
        $this->assertSame([$winner->id, $runnerUp->id], $standings->pluck('fantasy_team_id')->all());
        $this->assertSame([40, 20], $standings->pluck('championship_points')->all());
        $this->assertDatabaseCount('team_matchday_scores', 2);
        $this->assertDatabaseCount('team_matchday_score_details', 2);
        $this->assertDatabaseCount('standings', 2);
        $this->assertSourceRowsRemain($league, $matchday, $winner, $formation, $formationPlayer, $playerScore, $assignment);
    }

    public function test_failed_recalculation_rolls_back_derived_cleanup_and_preserves_sources(): void
    {
        [$league, $matchday] = $this->championship('classic');
        [$team, $formation, $formationPlayer, $playerScore, $assignment] = $this->submittedTeam($league, $matchday, 70);
        $league->championshipParticipants()->attach($team);
        $this->finalizer()->calculate($league, $matchday);
        $scores = TeamMatchdayScore::query()->get()->toArray();
        $details = TeamMatchdayScoreDetail::query()->get()->toArray();
        $standings = Standing::query()->get()->toArray();

        TeamMatchdayScore::creating(static function (): void {
            throw new RuntimeException('Forced scoring persistence failure.');
        });

        try {
            $this->finalizer()->calculate($league, $matchday);
            $this->fail('The forced recalculation failure did not propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced scoring persistence failure.', $exception->getMessage());
        } finally {
            TeamMatchdayScore::flushEventListeners();
        }

        $this->assertSame($scores, TeamMatchdayScore::query()->get()->toArray());
        $this->assertSame($details, TeamMatchdayScoreDetail::query()->get()->toArray());
        $this->assertSame($standings, Standing::query()->get()->toArray());
        $this->assertSourceRowsRemain($league, $matchday, $team, $formation, $formationPlayer, $playerScore, $assignment);
    }

    public function test_empty_matchday_is_marked_once_and_recalculation_remains_idempotent(): void
    {
        [$league, $matchday] = $this->championship('classic');

        $this->finalizer()->calculate($league, $matchday);
        $this->finalizer()->calculate($league, $matchday);

        $this->assertDatabaseCount('league_matchday_calculations', 1);
        $this->assertTrue(LeagueMatchdayCalculation::query()->where([
            'league_id' => $league->id,
            'matchday_id' => $matchday->id,
        ])->exists());
        $this->assertDatabaseCount('team_matchday_scores', 0);
        $this->assertDatabaseCount('team_matchday_score_details', 0);
    }

    public function test_historical_derived_score_is_a_calculated_fallback_until_recalculation_creates_marker(): void
    {
        [$league, $matchday] = $this->championship('classic');
        [$team] = $this->submittedTeam($league, $matchday, 70);
        $league->championshipParticipants()->attach($team);

        $this->finalizer()->finalize($matchday);
        $this->assertDatabaseCount('league_matchday_calculations', 0);
        MatchdayController::addCalculationCapabilities($matchday, $league, null);
        $this->assertTrue($matchday->is_calculated);

        $this->finalizer()->calculate($league, $matchday);

        $this->assertDatabaseCount('league_matchday_calculations', 1);
        $this->assertDatabaseCount('team_matchday_scores', 1);
        MatchdayController::addCalculationCapabilities($matchday, $league, null);
        $this->assertTrue($matchday->is_calculated);
    }

    /** @return array{League, Matchday} */
    private function championship(string $type): array
    {
        $season = Season::factory()->create();
        $matchday = $this->endedMatchday($season);
        $league = $this->league($type, $season, [
            'championship_start_matchday_id' => $matchday->id,
            'championship_started_at' => now()->subDays(2),
        ]);

        return [$league, $matchday];
    }

    private function league(string $type, Season $season, array $attributes = []): League
    {
        $league = League::factory()->create([
            'season_id' => $season->id,
            'league_type_id' => LeagueType::query()->where('key', $type)->value('id'),
            ...$attributes,
        ]);
        app(LeagueSettingsService::class)->initializeDefaults($league);

        return $league->fresh('type');
    }

    private function endedMatchday(Season $season): Matchday
    {
        return Matchday::factory()->create([
            'season_id' => $season->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
    }

    /** @return array{FantasyTeam, Formation, FormationPlayer, PlayerScore, FantasyTeamPlayer} */
    private function submittedTeam(League $league, Matchday $matchday, float $points): array
    {
        $user = User::factory()->create();
        $league->users()->attach($user, [
            'league_role_id' => LeagueRole::query()->where('key', 'participant')->value('id'),
            'joined_at' => now(),
        ]);
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $user)->create();
        $player = Player::factory()->create();
        $role = PlayerRole::query()->where('key', 'forward')->firstOrFail();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id,
            'player_role_id' => $role->id,
        ]);
        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
        ]);
        $formation = Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
            'formation_module_id' => FormationModule::query()->where('name', '4-3-3')->value('id'),
            'is_confirmed' => true,
            'submitted_at' => now()->subDays(2),
        ]);
        $formationPlayer = FormationPlayer::factory()->create([
            'formation_id' => $formation->id,
            'fantasy_team_player_id' => $assignment->id,
            'player_id' => $player->id,
            'player_role_id' => $role->id,
            'slot_type' => 'starter',
            'position_index' => 1,
        ]);
        $score = PlayerScore::factory()->create([
            'player_season_registration_id' => $registration->id,
            'matchday_id' => $matchday->id,
            'status' => PlayerScoreStatus::Confirmed,
            'final_score' => $points,
        ]);

        return [$team, $formation, $formationPlayer, $score, $assignment];
    }

    private function decimalSetting(League $league, string $key, float $value): void
    {
        $league->settings()->updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
    }

    private function finalizer(): FinalizeMatchday
    {
        return app(FinalizeMatchday::class);
    }

    private function assertSourceRowsRemain(
        League $league,
        Matchday $matchday,
        FantasyTeam $team,
        Formation $formation,
        FormationPlayer $formationPlayer,
        PlayerScore $playerScore,
        FantasyTeamPlayer $assignment,
    ): void {
        $this->assertTrue($league->fresh()->exists);
        $this->assertTrue($matchday->fresh()->exists);
        $this->assertTrue($team->fresh()->exists);
        $this->assertTrue($assignment->fresh()->exists);
        $this->assertTrue($formation->fresh()->exists);
        $this->assertTrue($formationPlayer->fresh()->exists);
        $this->assertTrue($playerScore->fresh()->exists);
    }
}
