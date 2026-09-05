<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Models\User;
use App\Services\League\InitializeClassicChampionship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClassicMatchdayApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_classic_matchday_api_exposes_only_the_championship_sequence(): void
    {
        [
            'league' => $league,
            'commissioner' => $commissioner,
            'past' => $past,
            'current' => $current,
            'upcoming' => $upcoming,
        ] = $this->classicContext();

        $unrelatedBeforeChampionship = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 100,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addDay(),
        ]);

        $expectedIds = $past
            ->concat([$current])
            ->concat($upcoming)
            ->pluck('id')
            ->all();

        $response = $this->actingAs($commissioner)
            ->getJson("/api/v1/leagues/{$league->id}/matchdays")
            ->assertOk();

        $this->assertSame(
            $expectedIds,
            $response->json('data.*.id'),
        );

        $this->assertSame(
            [
                'past',
                'past',
                'past',
                'current',
                'upcoming',
                'upcoming',
                'upcoming',
                'upcoming',
            ],
            $response->json('data.*.championship_state'),
        );

        $this->assertNotContains(
            $unrelatedBeforeChampionship->id,
            $response->json('data.*.id'),
        );
    }

    public function test_past_classic_results_expose_calculated_and_missing_formation_states(): void
    {
        [
            'league' => $league,
            'commissioner' => $commissioner,
            'teams' => $teams,
            'past' => $past,
        ] = $this->classicContext();

        $matchday = $past->get(1);

        $formation = Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $teams->first()->id,
            'matchday_id' => $matchday->id,
            'is_confirmed' => true,
            'submitted_at' => $matchday->starts_at->copy()->subHour(),
        ]);

        TeamMatchdayScore::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $teams->first()->id,
            'matchday_id' => $matchday->id,
            'formation_id' => $formation->id,
            'points' => 80,
            'status' => 'calculated',
            'calculated_at' => $matchday->ends_at->copy()->addHour(),
        ]);

        $response = $this->actingAs($commissioner)
            ->getJson(
                "/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/classic-results"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data.teams');

        $this->assertContains(
            'calculated',
            $response->json('data.teams.*.result_status'),
        );

        $this->assertContains(
            'missing_formation',
            $response->json('data.teams.*.result_status'),
        );
    }

    public function test_current_classic_results_do_not_expose_points_before_calculation(): void
    {
        [
            'league' => $league,
            'commissioner' => $commissioner,
            'teams' => $teams,
            'current' => $current,
        ] = $this->classicContext();

        Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $teams->first()->id,
            'matchday_id' => $current->id,
            'is_confirmed' => true,
            'submitted_at' => now(),
        ]);

        Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $teams->last()->id,
            'matchday_id' => $current->id,
            'is_confirmed' => false,
            'submitted_at' => null,
        ]);

        $response = $this->actingAs($commissioner)
            ->getJson(
                "/api/v1/leagues/{$league->id}/matchdays/{$current->id}/classic-results"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data.teams');

        $this->assertSame(
            1,
            collect($response->json('data.teams'))
                ->where('formation_submitted', true)
                ->count(),
        );

        $this->assertSame(
            [null],
            array_values(
                array_unique(
                    $response->json('data.teams.*.points')
                )
            ),
        );
    }

    public function test_exposed_past_matchdays_match_the_classic_counted_boundary(): void
    {
        [
            'league' => $league,
            'commissioner' => $commissioner,
        ] = $this->classicContext();

        $exposedPastIds = collect(
            $this->actingAs($commissioner)
                ->getJson("/api/v1/leagues/{$league->id}/matchdays")
                ->assertOk()
                ->json('data')
        )
            ->where('championship_state', 'past')
            ->pluck('id')
            ->all();

        $countedIds = Matchday::query()
            ->where('season_id', $league->season_id)
            ->where(
                'starts_at',
                '>=',
                $league->classicStartMatchday()->value('starts_at'),
            )
            ->where('ends_at', '<=', now())
            ->orderBy('number')
            ->pluck('id')
            ->all();

        $this->assertSame(
            $countedIds,
            $exposedPastIds,
        );
    }

    /**
     * @return array{
     *     league: League,
     *     commissioner: User,
     *     teams: \Illuminate\Support\Collection<int, FantasyTeam>,
     *     past: \Illuminate\Support\Collection<int, Matchday>,
     *     current: Matchday,
     *     upcoming: \Illuminate\Support\Collection<int, Matchday>
     * }
     */
    private function classicContext(): array
    {
        $commissioner = User::factory()->create();

        $league = League::factory()->create([
            'commissioner_user_id' => $commissioner->id,
            'league_type_id' => LeagueType::query()
                ->where('key', 'classic')
                ->value('id'),
        ]);

        $commissionerRoleId = LeagueRole::query()
            ->where('key', 'commissioner')
            ->value('id');

        $participantRoleId = LeagueRole::query()
            ->where('key', 'participant')
            ->value('id');

        $league->users()->attach($commissioner->id, [
            'league_role_id' => $commissionerRoleId,
            'joined_at' => now(),
        ]);

        $participant = User::factory()->create();

        $league->users()->attach($participant->id, [
            'league_role_id' => $participantRoleId,
            'joined_at' => now(),
        ]);

        $teams = collect([
            FantasyTeam::factory()
                ->forLeagueAndUser($league, $commissioner)
                ->create(),

            FantasyTeam::factory()
                ->forLeagueAndUser($league, $participant)
                ->create(),
        ]);

        /*
     * Initialize the championship while every Matchday is still in the future.
     */
        $initializationNow = now()->startOfDay();

        Carbon::setTestNow($initializationNow);

        $matchdays = collect(range(0, 7))
            ->map(function (int $index) use ($league, $initializationNow): Matchday {
                $startsAt = $initializationNow
                    ->copy()
                    ->addDay()
                    ->addWeeks($index)
                    ->setHour(18);

                return Matchday::factory()->create([
                    'season_id' => $league->season_id,
                    'number' => 200 + $index,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addDay(),
                ]);
            });

        $past = $matchdays->take(3)->values();
        $current = $matchdays->get(3);
        $upcoming = $matchdays->slice(4)->values();

        app(InitializeClassicChampionship::class)->handle(
            $league,
            $matchdays->first()->id,
        );

        /*
     * Simulate reaching the fourth Matchday.
     *
     * 200-202 => past
     * 203     => current
     * 204-207 => upcoming
     */
        Carbon::setTestNow(
            $current->starts_at->copy()->addHour()
        );

        return [
            'league' => $league->fresh(),
            'commissioner' => $commissioner,
            'teams' => $teams,
            'past' => $past,
            'current' => $current,
            'upcoming' => $upcoming,
        ];
    }
}
