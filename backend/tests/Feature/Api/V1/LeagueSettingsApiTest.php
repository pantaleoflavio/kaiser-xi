<?php

namespace Tests\Feature\Api\V1;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_commissioner_and_co_commissioner_can_view_and_update_budget_settings(): void
    {
        foreach (['commissioner', 'co_commissioner'] as $role) {
            [$league, $user] = $this->leagueWithMember($role);
            Sanctum::actingAs($user);

            $this->getJson("/api/v1/leagues/{$league->id}/settings")
                ->assertOk()
                ->assertJsonPath('data.initial_budget', 500)
                ->assertJsonPath('data.release_refund_percentage', 50);

            $this->patchJson("/api/v1/leagues/{$league->id}/settings", [
                'initial_budget' => 750,
                'release_refund_percentage' => 60,
            ])->assertOk()
                ->assertJsonPath('data.initial_budget', 750)
                ->assertJsonPath('data.release_refund_percentage', 60);
        }
    }

    public function test_participant_and_non_member_cannot_manage_settings(): void
    {
        [$league, $participant] = $this->leagueWithMember('participant');
        foreach ([$participant, User::factory()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['initial_budget' => 1])->assertForbidden();
        }
    }

    public function test_non_member_cannot_view_settings(): void
    {
        $league = League::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/leagues/{$league->id}/settings")->assertForbidden();
    }

    public function test_settings_validation(): void
    {
        [$league, $commissioner] = $this->leagueWithMember('commissioner');
        Sanctum::actingAs($commissioner);

        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => -1])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 101])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['release_refund_percentage' => 12.5])->assertUnprocessable()->assertJsonValidationErrors('release_refund_percentage');
        $this->patchJson("/api/v1/leagues/{$league->id}/settings", ['initial_budget' => -1])->assertUnprocessable()->assertJsonValidationErrors('initial_budget');
    }

    private function leagueWithMember(string $role): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);

        return [$league, $user];
    }
}
