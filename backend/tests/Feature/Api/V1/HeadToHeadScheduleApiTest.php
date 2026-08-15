<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueInvitation;
use App\Models\LeagueRole;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\Season;
use App\Models\User;
use App\Services\League\GenerateHeadToHeadSchedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeadToHeadScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manager_initializes_schedule_once_without_filling_capacity(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(4, 7, 10);
        Sanctum::actingAs($league->commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", [
            'start_matchday_id' => $matchdays[0]->id,
        ])->assertOk()
            ->assertJsonPath('data.initialized', true)
            ->assertJsonPath('data.start_matchday.id', $matchdays[0]->id)
            ->assertJsonPath('data.participant_count', 4)
            ->assertJsonCount(7, 'data.matchdays');

        $league->refresh();
        $this->assertNotNull($league->h2h_schedule_generated_at);
        $this->assertSame($matchdays[0]->id, $league->h2h_start_matchday_id);
        $this->assertDatabaseCount('fantasy_matches', 14);
        $this->assertDatabaseCount('fantasy_match_results', 0);
        foreach ($matchdays as $matchday) {
            $fixtures = FantasyMatch::query()->where('matchday_id', $matchday->id)->get();
            $this->assertCount(4, $fixtures->flatMap(fn($match) => [$match->home_fantasy_team_id, $match->away_fantasy_team_id])->unique());
        }

        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", [
            'start_matchday_id' => $matchdays[1]->id,
        ])->assertConflict()->assertHeader('X-Error-Code', 'league_schedule_already_initialized');
        $this->assertSame(14, FantasyMatch::query()->where('league_id', $league->id)->count());
    }

    public function test_schedule_exposes_authoritative_calculated_and_missing_results(): void
    {
        [$league,, $matchdays] = $this->leagueWithScheduleInputs(4, 1, 6);
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", [
            'start_matchday_id' => $matchdays[0]->id,
        ])->assertOk();

        $matches = FantasyMatch::query()->where('league_id', $league->id)->orderBy('id')->get();
        FantasyMatchResult::factory()->for($matches->first())->create([
            'home_points' => 77,
            'away_points' => 69.5,
            'home_goals' => 2,
            'away_goals' => 1,
            'result_status' => 'calculated',
            'calculated_at' => now(),
        ]);

        $this->getJson("/api/v1/leagues/{$league->id}/head-to-head-schedule")
            ->assertOk()
            ->assertJsonPath('data.matchdays.0.fixtures.0.result.status', 'calculated')
            ->assertJsonPath('data.matchdays.0.fixtures.0.result.home_goals', 2)
            ->assertJsonPath('data.matchdays.0.fixtures.0.result.away_goals', 1)
            ->assertJsonPath('data.matchdays.0.fixtures.0.result.home_points', '77.00')
            ->assertJsonPath('data.matchdays.0.fixtures.1.result', null);
    }

    public function test_six_teams_repeat_the_ten_round_cycle_across_twenty_three_matchdays(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(6, 23, 12);
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertOk();

        $rounds = collect($matchdays)->map(fn($matchday) => FantasyMatch::query()
            ->where('league_id', $league->id)->where('matchday_id', $matchday->id)
            ->orderBy('id')->get(['home_fantasy_team_id', 'away_fantasy_team_id'])
            ->map(fn($match) => [$match->home_fantasy_team_id, $match->away_fantasy_team_id])->all())->all();

        $this->assertCount(23, $rounds);
        $this->assertSame(array_slice($rounds, 0, 10), array_slice($rounds, 10, 10));
        $this->assertSame(array_slice($rounds, 0, 3), array_slice($rounds, 20, 3));
        $this->assertDatabaseCount('fantasy_matches', 69);
    }

    public function test_invalid_type_team_count_and_start_matchday_are_rejected_without_partial_state(): void
    {
        foreach (['classic', 'formula_one'] as $type) {
            [$league,, $matchdays] = $this->leagueWithScheduleInputs(2, 2, 4, $type);
            Sanctum::actingAs($league->commissioner);
            $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertConflict();
            $this->assertNull($league->refresh()->h2h_schedule_generated_at);
        }

        [$league,, $matchdays] = $this->leagueWithScheduleInputs(1, 2, 4);
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertConflict();

        [$league,, $matchdays] = $this->leagueWithScheduleInputs(2, 2, 4);
        $foreign = Matchday::factory()->create(['number' => 99, 'starts_at' => now()->addDay()]);
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $foreign->id])->assertConflict();
        $matchdays[0]->update(['starts_at' => now()->subMinute()]);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertConflict();
        $this->assertNull($league->refresh()->h2h_schedule_generated_at);
        $this->assertSame(0, FantasyMatch::query()->where('league_id', $league->id)->count());
    }

    public function test_schedule_freezes_invitations_acceptance_and_team_creation(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(2, 3, 8);
        $recipient = User::factory()->create();
        $invitation = LeagueInvitation::factory()->for($league)->create([
            'invited_user_id' => $recipient->id,
            'created_by_user_id' => $league->commissioner_user_id,
        ]);
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertOk();
        $fixtureIds = FantasyMatch::query()->where('league_id', $league->id)->pluck('id')->all();

        $this->postJson("/api/v1/leagues/{$league->id}/invitations", ['email' => User::factory()->create()->email, 'role' => 'participant'])
            ->assertConflict()->assertHeader('X-Error-Code', 'league_schedule_already_initialized');
        Sanctum::actingAs($recipient);
        $this->postJson("/api/v1/invitations/{$invitation->id}/accept")
            ->assertConflict()->assertHeader('X-Error-Code', 'league_schedule_already_initialized');
        $this->attach($league, $recipient, 'participant');
        $this->postJson("/api/v1/leagues/{$league->id}/fantasy-teams", ['name' => 'Replacement'])
            ->assertConflict()->assertHeader('X-Error-Code', 'league_schedule_already_initialized');

        Sanctum::actingAs($league->commissioner);
        $removed = $teams[1];
        $this->deleteJson("/api/v1/leagues/{$league->id}/members/{$removed->user_id}")->assertNoContent();
        $this->assertDatabaseHas('fantasy_teams', ['id' => $removed->id]);
        $this->assertSame($fixtureIds, FantasyMatch::query()->where('league_id', $league->id)->pluck('id')->all());
    }

    public function test_insert_failure_rolls_back_fixtures_and_initialization_state(): void
    {
        [$league,, $matchdays] = $this->leagueWithScheduleInputs(4, 3, 8);
        $inserts = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$inserts): void {
            if (str_contains($query->sql, 'insert into "fantasy_matches"') && ++$inserts === 2) {
                throw new \RuntimeException('Simulated fixture insert failure.');
            }
        });

        try {
            $this->app->make(GenerateHeadToHeadSchedule::class)->handle($league, $matchdays[0]->id);
            $this->fail('Schedule generation should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated fixture insert failure.', $exception->getMessage());
        } finally {
            Event::forget(QueryExecuted::class);
        }

        $this->assertSame(0, FantasyMatch::query()->where('league_id', $league->id)->count());
        $this->assertNull($league->refresh()->h2h_schedule_generated_at);
        $this->assertNull($league->h2h_start_matchday_id);
    }

    public function test_participant_cannot_initialize_and_odd_byes_are_not_persisted(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(5, 5, 8);
        Sanctum::actingAs($teams[1]->user);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertForbidden();
        Sanctum::actingAs($league->commissioner);
        $this->postJson("/api/v1/leagues/{$league->id}/head-to-head-schedule", ['start_matchday_id' => $matchdays[0]->id])->assertOk();
        foreach ($matchdays as $matchday) {
            $this->assertSame(2, FantasyMatch::query()->where('league_id', $league->id)->where('matchday_id', $matchday->id)->count());
        }
        $this->assertSame(5, FantasyTeam::query()->where('league_id', $league->id)->count());
    }

    public function test_database_rejects_self_matches_and_exact_duplicates(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(2, 1, 4);
        $fixture = FantasyMatch::query()->create([
            'league_id' => $league->id,
            'matchday_id' => $matchdays[0]->id,
            'home_fantasy_team_id' => $teams[0]->id,
            'away_fantasy_team_id' => $teams[1]->id,
        ]);
        $this->expectException(QueryException::class);
        FantasyMatch::query()->insert($fixture->only(['league_id', 'matchday_id', 'home_fantasy_team_id', 'away_fantasy_team_id']));
    }

    public function test_domain_rejects_a_self_match(): void
    {
        [$league, $teams, $matchdays] = $this->leagueWithScheduleInputs(2, 1, 4);
        $this->expectException(\DomainException::class);
        FantasyMatch::query()->create([
            'league_id' => $league->id,
            'matchday_id' => $matchdays[0]->id,
            'home_fantasy_team_id' => $teams[0]->id,
            'away_fantasy_team_id' => $teams[0]->id,
        ]);
    }

    private function leagueWithScheduleInputs(int $teamCount, int $matchdayCount, int $capacity, string $type = 'head_to_head'): array
    {
        $season = Season::factory()->create();
        $league = League::factory()->create([
            'season_id' => $season->id,
            'league_type_id' => LeagueType::query()->where('key', $type)->value('id'),
            'max_participants' => $capacity,
        ]);
        $teams = [];
        for ($index = 0; $index < $teamCount; $index++) {
            $user = $index === 0 ? $league->commissioner : User::factory()->create();
            $this->attach($league, $user, $index === 0 ? 'commissioner' : 'participant');
            $teams[] = FantasyTeam::factory()->forLeagueAndUser($league, $user)->create();
        }
        $matchdays = [];
        for ($number = 1; $number <= $matchdayCount; $number++) {
            $matchdays[] = Matchday::factory()->create([
                'season_id' => $season->id,
                'number' => $number,
                'starts_at' => now()->addDays($number),
                'ends_at' => now()->addDays($number)->addHour(),
            ]);
        }

        return [$league, $teams, $matchdays];
    }

    private function attach(League $league, User $user, string $role): void
    {
        if (! $league->memberships()->where('user_id', $user->id)->exists()) {
            $league->users()->attach($user->id, [
                'league_role_id' => LeagueRole::query()->where('key', $role)->value('id'),
                'joined_at' => now(),
            ]);
        }
    }
}
