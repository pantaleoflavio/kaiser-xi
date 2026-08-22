<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Matchday;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        [$league, $matchday] = $this->initializedClassicLeague(now()->subHour());
        $url = "/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/calculate";
        $participant = $this->member($league, 'participant');
        $coCommissioner = $this->member($league, 'co_commissioner');

        Sanctum::actingAs($participant);
        $this->postJson($url)->assertForbidden();

        Sanctum::actingAs($coCommissioner);
        $this->postJson($url)->assertOk()
            ->assertJsonPath('data.is_calculated', true)
            ->assertJsonPath('data.can_recalculate', true);

        $this->assertDatabaseCount('league_matchday_calculations', 1);

        Sanctum::actingAs($league->commissioner);
        $this->postJson($url)->assertOk();
        $this->assertDatabaseCount('league_matchday_calculations', 1);
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

    /** @return array{League, Matchday} */
    private function initializedClassicLeague(CarbonInterface $endsAt): array
    {
        $league = League::factory()->create();
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt,
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
