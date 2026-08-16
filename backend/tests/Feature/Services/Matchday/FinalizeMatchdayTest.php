<?php

namespace Tests\Feature\Services\Matchday;

use App\Enums\PlayerScoreStatus;
use App\Events\MatchdayReadyForCalculation;
use App\Jobs\FinalizeMatchdayJob;
use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\LeagueRole;
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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FinalizeMatchdayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_finalizes_multiple_leagues_idempotently_and_recalculates_corrected_scores(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        [$firstLeague, $firstMatch, $firstHomeScore] = $this->headToHeadFixture($season, $matchday, 70, 60);
        [$secondLeague, $secondMatch] = $this->headToHeadFixture($season, $matchday, 60, 70);
        $finalizer = app(FinalizeMatchday::class);

        $finalizer->finalize($matchday);

        $this->assertSame([1, 0], [$firstMatch->result->home_goals, $firstMatch->result->away_goals]);
        $this->assertSame([0, 1], [$secondMatch->result->home_goals, $secondMatch->result->away_goals]);
        $this->assertSame(3, Standing::query()->where('league_id', $firstLeague->id)->orderBy('position')->first()->points_total);
        $this->assertSame(3, Standing::query()->where('league_id', $secondLeague->id)->orderBy('position')->first()->points_total);
        $counts = $this->aggregateCounts();

        $finalizer->finalize($matchday);
        $this->assertSame($counts, $this->aggregateCounts());

        $firstHomeScore->update(['final_score' => 60]);
        $finalizer->finalize($matchday);

        $this->assertSame('60.00', $firstMatch->fresh()->result->home_points);
        $this->assertSame([0, 0], [$firstMatch->fresh()->result->home_goals, $firstMatch->fresh()->result->away_goals]);
        $this->assertSame([1, 1], Standing::query()->where('league_id', $firstLeague->id)->orderBy('fantasy_team_id')->pluck('points_total')->all());
        $this->assertSame([0, 1], [$secondMatch->fresh()->result->home_goals, $secondMatch->fresh()->result->away_goals]);
        $this->assertSame($counts, $this->aggregateCounts());
    }

    public function test_non_head_to_head_leagues_only_receive_team_scores(): void
    {
        $season = Season::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $season->id]);
        foreach (['classic', 'formula_one'] as $type) {
            $league = League::factory()->create([
                'season_id' => $season->id,
                'league_type_id' => LeagueType::query()->where('key', $type)->value('id'),
            ]);
            app(LeagueSettingsService::class)->initializeDefaults($league);
            $team = FantasyTeam::factory()->create(['league_id' => $league->id]);
            $this->submittedFormation($league, $team, $matchday, 50);
        }

        app(FinalizeMatchday::class)->finalize($matchday);

        $this->assertSame(2, TeamMatchdayScore::query()->count());
        $this->assertSame(0, FantasyMatchResult::query()->count());
        $this->assertSame(0, Standing::query()->count());
    }

    public function test_ready_event_queues_one_job_and_job_executes_the_workflow(): void
    {
        Queue::fake();
        $matchday = Matchday::factory()->create();

        MatchdayReadyForCalculation::dispatch($matchday->id);

        Queue::assertPushed(FinalizeMatchdayJob::class, 1);
        Queue::assertPushed(fn(FinalizeMatchdayJob $job): bool => $job->matchdayId === $matchday->id);

        (new FinalizeMatchdayJob($matchday->id))->handle(app(FinalizeMatchday::class));
    }

    /** @return array{League, FantasyMatch, PlayerScore} */
    private function headToHeadFixture(Season $season, Matchday $matchday, float $homePoints, float $awayPoints): array
    {
        $league = League::factory()->create([
            'season_id' => $season->id,
            'league_type_id' => LeagueType::query()->where('key', 'head_to_head')->value('id'),
            'h2h_schedule_generated_at' => now(),
        ]);
        app(LeagueSettingsService::class)->initializeDefaults($league);
        $teams = collect([User::factory()->create(), User::factory()->create()])->map(function (User $user) use ($league): FantasyTeam {
            $league->users()->attach($user, ['league_role_id' => LeagueRole::query()->where('key', 'participant')->value('id'), 'joined_at' => now()]);

            return FantasyTeam::factory()->forLeagueAndUser($league, $user)->create();
        });
        $homeScore = $this->submittedFormation($league, $teams[0], $matchday, $homePoints);
        $this->submittedFormation($league, $teams[1], $matchday, $awayPoints);
        $match = FantasyMatch::factory()->create([
            'league_id' => $league->id,
            'matchday_id' => $matchday->id,
            'home_fantasy_team_id' => $teams[0]->id,
            'away_fantasy_team_id' => $teams[1]->id,
        ]);

        return [$league, $match, $homeScore];
    }

    private function submittedFormation(League $league, FantasyTeam $team, Matchday $matchday, float $points): PlayerScore
    {
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
            'submitted_at' => now(),
        ]);
        FormationPlayer::factory()->create([
            'formation_id' => $formation->id,
            'fantasy_team_player_id' => $assignment->id,
            'player_id' => $player->id,
            'player_role_id' => $role->id,
            'slot_type' => 'starter',
            'position_index' => 1,
        ]);

        return PlayerScore::factory()->create([
            'player_season_registration_id' => $registration->id,
            'matchday_id' => $matchday->id,
            'status' => PlayerScoreStatus::Confirmed,
            'final_score' => $points,
        ]);
    }

    /** @return array{int, int, int, int} */
    private function aggregateCounts(): array
    {
        return [
            TeamMatchdayScore::query()->count(),
            TeamMatchdayScoreDetail::query()->count(),
            FantasyMatchResult::query()->count(),
            Standing::query()->count(),
        ];
    }
}
