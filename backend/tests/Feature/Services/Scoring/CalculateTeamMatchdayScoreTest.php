<?php

namespace Tests\Feature\Services\Scoring;

use App\Enums\PlayerScoreStatus;
use App\Exceptions\SubmittedFormationNotFound;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use App\Services\Formation\SaveFormationService;
use App\Services\League\LeagueSettingsService;
use App\Services\Scoring\CalculateTeamMatchdayScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CalculateTeamMatchdayScoreTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, PlayerSeasonRegistration> */
    private array $registrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_scores_playable_starters_from_final_score_and_ignores_unused_bench(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        foreach ($starters as $index => $assignment) {
            $this->score($formation, $assignment, $index === 0 ? 6.5 : 1.0);
        }
        $this->score($formation, $bench[0], 99.0);

        $result = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);

        $this->assertSame('16.50', $result->base_points);
        $this->assertSame('16.50', $result->points);
        $this->assertSame('calculated', $result->status);
        $this->assertNotNull($result->calculated_at);
        $this->assertSame('0.00', $result->details->firstWhere('player_id', $this->playerId($bench[0]))->points);
    }

    public function test_unavailable_starter_is_replaced_and_only_incoming_score_contributes(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        foreach (array_slice($starters, 1) as $assignment) {
            $this->score($formation, $assignment, 1.0);
        }
        $this->score($formation, $bench[0], 4.5);

        $result = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);
        $incoming = $result->details->firstWhere('player_id', $this->playerId($bench[0]));
        $outgoing = $result->details->firstWhere('player_id', $this->playerId($starters[0]));

        $this->assertSame('14.50', $result->base_points);
        $this->assertSame('4.50', $incoming->points);
        $this->assertTrue($incoming->was_bench);
        $this->assertTrue($incoming->was_used_as_substitute);
        $this->assertSame($outgoing->player_id, $incoming->replaced_player_id);
        $this->assertSame('0.00', $outgoing->points);
    }

    public function test_unresolved_unavailable_starter_contributes_zero(): void
    {
        [$formation, $starters] = $this->formation([]);
        foreach (array_slice($starters, 1) as $assignment) {
            $this->score($formation, $assignment, 1.0);
        }

        $result = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);

        $this->assertSame('10.00', $result->points);
        $this->assertSame('0.00', $result->details->firstWhere('player_id', $this->playerId($starters[0]))->points);
    }

    public function test_real_captain_bonus_only_applies_to_playable_effective_players(): void
    {
        [$formation, $starters, $bench] = $this->formation(['defender']);
        $this->setting($formation->league, LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED, LeagueSetting::booleanPayload(true));
        $this->setting($formation->league, LeagueSetting::REAL_CAPTAIN_BONUS_POINTS, LeagueSetting::decimalPayload(0.5));
        foreach (array_slice($starters, 1) as $assignment) {
            $this->score($formation, $assignment, 1.0);
        }
        $this->score($formation, $bench[0], 0.0, true);

        $result = $this->calculator()->calculate($formation->fantasyTeam->fresh(), $formation->matchday);

        $this->assertSame('10.00', $result->base_points);
        $this->assertSame('10.50', $result->points);
        $this->assertSame('0.50', $result->details->firstWhere('player_id', $this->playerId($bench[0]))->points);
        $this->assertSame('0.00', $result->details->firstWhere('player_id', $this->playerId($starters[0]))->points);
    }

    public function test_disabled_bonus_and_non_captain_do_not_adjust_points(): void
    {
        [$formation, $starters] = $this->formation([]);
        foreach ($starters as $assignment) {
            $this->score($formation, $assignment, 1.0, $assignment === $starters[0]);
        }

        $disabled = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);
        $this->assertSame('11.00', $disabled->points);

        $this->setting($formation->league, LeagueSetting::REAL_CAPTAIN_BONUS_ENABLED, LeagueSetting::booleanPayload(true));
        $this->score($formation, $starters[0], 1.0, false);
        $nonCaptain = $this->calculator()->calculate($formation->fantasyTeam->fresh(), $formation->matchday);
        $this->assertSame('11.00', $nonCaptain->points);
    }

    public function test_recalculation_updates_one_aggregate_replaces_details_and_uses_historical_assignment(): void
    {
        [$formation, $starters] = $this->formation([]);
        foreach ($starters as $assignment) {
            $this->score($formation, $assignment, 1.0);
        }
        FantasyTeamPlayer::query()->findOrFail($starters[0])->update(['released_at' => now()]);
        $first = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);
        $firstCalculatedAt = $first->calculated_at;
        $this->travel(1)->second();
        $this->score($formation, $starters[0], 2.0);

        $second = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('12.00', $second->points);
        $this->assertTrue($second->calculated_at->greaterThan($firstCalculatedAt));
        $this->assertSame(1, TeamMatchdayScore::query()->count());
        $this->assertSame(11, TeamMatchdayScoreDetail::query()->count());
    }

    public function test_failed_detail_replacement_rolls_back_aggregate_and_old_details(): void
    {
        [$formation, $starters] = $this->formation([]);
        foreach ($starters as $assignment) {
            $this->score($formation, $assignment, 1.0);
        }
        $original = $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);
        $this->score($formation, $starters[0], 8.0);
        TeamMatchdayScoreDetail::creating(fn() => throw new RuntimeException('forced detail failure'));

        try {
            $this->calculator()->calculate($formation->fantasyTeam, $formation->matchday);
            $this->fail('The forced persistence failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced detail failure', $exception->getMessage());
        }

        $this->assertSame('11.00', $original->fresh()->points);
        $this->assertSame(11, $original->details()->count());
    }

    public function test_no_submitted_formation_is_an_explicit_error(): void
    {
        $team = FantasyTeam::factory()->create();
        $matchday = Matchday::factory()->create(['season_id' => $team->league->season_id]);

        $this->expectException(SubmittedFormationNotFound::class);
        $this->calculator()->calculate($team, $matchday);
    }

    /** @return array{Formation, list<int>, list<int>} */
    private function formation(array $benchRoles): array
    {
        $league = League::factory()->create();
        app(LeagueSettingsService::class)->initializeDefaults($league);
        $team = FantasyTeam::factory()->create(['league_id' => $league->id]);
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $module = FormationModule::query()->with('requirements.playerRole')->where('name', '4-3-3')->firstOrFail();
        $starterRows = [];
        foreach ($module->requirements as $requirement) {
            for ($i = 0; $i < $requirement->required_count; $i++) {
                $starterRows[] = [
                    'id' => $this->assignment($league, $team, $requirement->playerRole->key)->id,
                    'role' => $requirement->playerRole->key,
                ];
            }
        }
        $starters = collect($starterRows)
            ->sortBy(fn(array $row): int => $row['role'] === 'defender' ? 0 : 1)
            ->pluck('id')
            ->all();
        $bench = array_map(fn(string $role): int => $this->assignment($league, $team, $role)->id, $benchRoles);
        $formation = app(SaveFormationService::class)->save($league, $matchday, $team, [
            'formation_module_id' => $module->id,
            'starters' => $starters,
            'bench' => collect($bench)->map(fn(int $id, int $index): array => ['fantasy_team_player_id' => $id, 'order' => $index + 1])->all(),
        ]);
        $formation->update(['is_confirmed' => true, 'submitted_at' => now()]);

        return [$formation->fresh(), $starters, $bench];
    }

    private function assignment(League $league, FantasyTeam $team, string $role): FantasyTeamPlayer
    {
        $player = Player::factory()->create();
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id,
            'player_role_id' => PlayerRole::query()->where('key', $role)->firstOrFail()->id,
        ]);
        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
        ]);
        $this->registrations[$assignment->id] = $registration;

        return $assignment;
    }

    private function score(Formation $formation, int $assignment, float $points, bool $captain = false): void
    {
        PlayerScore::query()->updateOrCreate(
            ['player_season_registration_id' => $this->registrations[$assignment]->id, 'matchday_id' => $formation->matchday_id],
            ['status' => PlayerScoreStatus::Confirmed, 'final_score' => $points, 'is_captain' => $captain],
        );
    }

    private function playerId(int $assignment): int
    {
        return (int) FantasyTeamPlayer::query()->findOrFail($assignment)->player_id;
    }

    private function setting(League $league, string $key, array $value): void
    {
        $league->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function calculator(): CalculateTeamMatchdayScore
    {
        return app(CalculateTeamMatchdayScore::class);
    }
}
