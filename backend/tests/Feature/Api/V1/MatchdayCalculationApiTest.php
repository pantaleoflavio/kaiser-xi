<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CalculationStatus;
use App\Jobs\CalculateLeagueMatchdayJob;
use App\Models\League;
use App\Models\LeagueMatchdayCalculation;
use App\Models\LeagueRole;
use App\Models\Matchday;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchdayCalculationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_only_commissioners_can_calculate_an_ended_initialized_matchday(): void
    {
        Queue::fake();
        [$league, $matchday] = $this->initializedClassicLeague(now()->subHour());
        $url = "/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/calculate";
        $participant = $this->member($league, 'participant');
        $coCommissioner = $this->member($league, 'co_commissioner');

        Sanctum::actingAs($participant);
        $this->postJson($url)->assertForbidden();

        Sanctum::actingAs($coCommissioner);
        $this->postJson($url)->assertAccepted()
            ->assertJsonPath('data.is_calculated', false)
            ->assertJsonPath('data.calculation_status', 'queued');
        Queue::assertPushed(CalculateLeagueMatchdayJob::class, 1);

        $this->assertDatabaseCount('league_matchday_calculations', 1);

        Sanctum::actingAs($league->commissioner);
        $this->postJson($url)->assertAccepted();
        Queue::assertPushed(CalculateLeagueMatchdayJob::class, 1);
        $this->assertDatabaseCount('league_matchday_calculations', 1);
        $this->assertDatabaseHas('league_matchday_calculations', ['status' => CalculationStatus::Queued->value, 'calculated_at' => null]);
    }

    public function test_calculation_before_the_matchday_end_is_rejected(): void
    {
        [$league, $matchday] = $this->initializedClassicLeague(now()->addHour());
        Sanctum::actingAs($league->commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/calculate")
            ->assertConflict()
            ->assertJsonPath('message', 'The matchday has not ended yet.');

        $this->assertDatabaseCount('league_matchday_calculations', 0);
    }

    public function test_matchday_from_another_league_season_is_not_found(): void
    {
        [$league] = $this->initializedClassicLeague(now()->subHour());
        $otherMatchday = Matchday::factory()->create(['ends_at' => now()->subHour()]);
        Sanctum::actingAs($league->commissioner);

        $this->postJson("/api/v1/leagues/{$league->id}/matchdays/{$otherMatchday->id}/calculate")
            ->assertNotFound();
    }

    public function test_only_the_first_ended_locked_uncalculated_matchday_waits_for_unlock(): void
    {
        [$league, $calculated] = $this->initializedClassicLeague(now()->subHours(20));
        LeagueMatchdayCalculation::create([
            'league_id' => $league->id,
            'matchday_id' => $calculated->id,
            'status' => CalculationStatus::Completed,
            'calculated_at' => now()->subHours(19),
        ]);
        $firstPending = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 2,
            'starts_at' => now()->subHours(18),
            'ends_at' => now()->subHours(16),
            'calculation_unlocked_at' => null,
        ]);
        $laterPending = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 3,
            'starts_at' => now()->subHours(15),
            'ends_at' => now()->subHours(13),
            'calculation_unlocked_at' => null,
        ]);
        $future = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 4,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'calculation_unlocked_at' => null,
        ]);
        Sanctum::actingAs($league->commissioner);

        $data = $this->getJson("/api/v1/leagues/{$league->id}/matchdays")
            ->assertOk()
            ->json('data');
        $byId = collect($data)->keyBy('id');

        $this->assertFalse($byId[$calculated->id]['is_waiting_for_calculation_unlock']);
        $this->assertFalse($byId[$firstPending->id]['is_calculated']);
        $this->assertNull($byId[$firstPending->id]['calculation_status']);
        $this->assertTrue($byId[$firstPending->id]['is_waiting_for_calculation_unlock']);
        $this->assertFalse($byId[$laterPending->id]['is_calculated']);
        $this->assertNull($byId[$laterPending->id]['calculation_status']);
        $this->assertFalse($byId[$laterPending->id]['is_waiting_for_calculation_unlock']);
        $this->assertFalse($byId[$future->id]['is_waiting_for_calculation_unlock']);
    }

    public function test_unlocked_first_pending_matchday_has_normal_calculate_capability_without_waiting_signal(): void
    {
        [$league, $matchday] = $this->initializedClassicLeague(now()->subHour());
        Sanctum::actingAs($league->commissioner);

        $this->getJson("/api/v1/leagues/{$league->id}/matchdays")
            ->assertOk()
            ->assertJsonPath('data.0.can_calculate', true)
            ->assertJsonPath('data.0.is_waiting_for_calculation_unlock', false);
    }

    /** @return array{League, Matchday} */
    private function initializedClassicLeague(CarbonInterface $endsAt): array
    {
        $league = League::factory()->create();
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt,
            'calculation_unlocked_at' => now(),
        ]);
        $league->update([
            'championship_start_matchday_id' => $matchday->id,
            'championship_started_at' => now()->subDays(2),
        ]);
        $league->users()->attach($league->commissioner, [
            'league_role_id' => LeagueRole::where('key', 'commissioner')->value('id'),
            'joined_at' => now(),
        ]);

        return [$league->fresh(), $matchday];
    }

    private function member(League $league, string $role): User
    {
        $user = User::factory()->create();
        $league->users()->attach($user, [
            'league_role_id' => LeagueRole::where('key', $role)->value('id'),
            'joined_at' => now(),
        ]);

        return $user;
    }
}
