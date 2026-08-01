<?php

namespace Tests\Feature\Api\V1;

use App\Models\LeagueType;
use App\Models\User;
use Database\Seeders\LeagueTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueTypeLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_seeded_league_types_safely_in_key_order(): void
    {
        $this->seed(LeagueTypeSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/league-types')->assertOk();
        $types = LeagueType::query()->orderBy('key')->get();

        $response->assertExactJson([
            'data' => $types->map(fn (LeagueType $type) => [
                'id' => $type->id,
                'key' => $type->key,
                'label' => $type->label,
            ])->all(),
        ]);
        $this->assertSame(
            ['classic', 'formula_one', 'head_to_head'],
            $response->json('data.*.key')
        );
    }

    public function test_guest_cannot_list_league_types(): void
    {
        $this->getJson('/api/v1/league-types')->assertUnauthorized();
    }
}