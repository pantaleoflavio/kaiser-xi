<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormulaOneChampionshipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_uninitialized_championship_exposes_only_future_start_matchdays(): void
    {
        $this->seedReferenceData();
        $commissioner = User::factory()->create();
        $league = League::factory()->create([
            'commissioner_user_id' => $commissioner->id,
            'league_type_id' => LeagueType::query()->where('key', 'formula_one')->value('id'),
        ]);
        $league->users()->attach($commissioner, [
            'league_role_id' => LeagueRole::query()->where('key', 'commissioner')->value('id'),
            'joined_at' => now(),
        ]);
        Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHour(),
        ]);
        $future = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'number' => 2,
            'name' => 'Future Matchday',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        Matchday::factory()->create(['number' => 3, 'starts_at' => now()->addDay()]);

        Sanctum::actingAs($commissioner);

        $this->getJson("/api/v1/leagues/{$league->id}/formula-one-championship")
            ->assertOk()
            ->assertJsonCount(1, 'data.available_start_matchdays')
            ->assertJsonPath('data.available_start_matchdays.0.id', $future->id)
            ->assertJsonPath('data.available_start_matchdays.0.name', 'Future Matchday');
    }
}
