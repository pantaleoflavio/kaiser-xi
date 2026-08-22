<?php

namespace Tests\Feature\Api\V1;

use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeasonLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_seasons_with_safe_response_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $competition = RealCompetition::factory()->create([
            'name' => 'Serie A',
            'code' => 'serie_a',
        ]);
        $season = Season::factory()->create([
            'real_competition_id' => $competition->id,
            'name' => '2026/2027',
            'starts_at' => '2026-08-01',
            'ends_at' => '2027-05-31',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/seasons')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $season->id,
                    'name' => '2026/2027',
                    'starts_at' => '2026-08-01',
                    'ends_at' => '2027-05-31',
                    'is_active' => true,
                    'competition' => [
                        'id' => $competition->id,
                        'name' => 'Serie A',
                        'code' => 'serie_a',
                    ],
                ]],
            ]);
    }

    public function test_guest_cannot_list_seasons(): void
    {
        $this->getJson('/api/v1/seasons')->assertUnauthorized();
    }

    public function test_active_filter_returns_only_requested_seasons(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Season::factory()->create(['is_active' => true]);
        $inactive = Season::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/seasons?active=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->id)
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_competition_filter_returns_only_matching_seasons(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $competition = RealCompetition::factory()->create();
        $matching = Season::factory()->create(['real_competition_id' => $competition->id]);
        Season::factory()->create();

        $this->getJson('/api/v1/seasons?real_competition_id='.$competition->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.competition.id', $competition->id);
    }

    public function test_seasons_have_deterministic_active_then_most_recent_ordering(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $inactive = Season::factory()->create(['starts_at' => '2027-08-01', 'is_active' => false]);
        $olderActive = Season::factory()->create(['starts_at' => '2025-08-01', 'is_active' => true]);
        $newerActive = Season::factory()->create(['starts_at' => '2026-08-01', 'is_active' => true]);

        $this->getJson('/api/v1/seasons')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerActive->id)
            ->assertJsonPath('data.1.id', $olderActive->id)
            ->assertJsonPath('data.2.id', $inactive->id);
    }

    public function test_filters_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/seasons?active=not-a-boolean')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active');

        $this->getJson('/api/v1/seasons?real_competition_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('real_competition_id');
    }

    public function test_competitions_are_eager_loaded_in_one_query(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Season::factory()->count(3)->create();
        $lookupQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$lookupQueries): void {
            if (str_contains($query->sql, 'from "seasons"') || str_contains($query->sql, 'from "real_competitions"')) {
                $lookupQueries++;
            }
        });

        $this->getJson('/api/v1/seasons')->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(2, $lookupQueries);
    }
}
