<?php

namespace Tests\Feature\Domain;

use App\Models\League;
use App\Models\LeagueRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_can_be_created_with_season_type_status_and_commissioner(): void
    {
        $this->seedReferenceData();

        $commissioner = User::factory()->create();
        $league = League::factory()->create(['commissioner_user_id' => $commissioner->id]);

        $this->assertNotNull($league->season);
        $this->assertNotNull($league->type);
        $this->assertNotNull($league->status);
        $this->assertTrue($league->commissioner->is($commissioner));
    }

    public function test_user_can_be_attached_to_league_with_league_role(): void
    {
        $this->seedReferenceData();

        $league = League::factory()->create();
        $member = User::factory()->create();
        $participantRole = LeagueRole::query()->where('key', 'participant')->firstOrFail();

        $league->users()->attach($member->id, [
            'league_role_id' => $participantRole->id,
            'joined_at' => now(),
        ]);

        $attachedUser = $league->fresh()->users()->firstOrFail();

        $this->assertTrue($attachedUser->is($member));
        $this->assertSame($participantRole->id, $attachedUser->pivot->league_role_id);
    }
}
