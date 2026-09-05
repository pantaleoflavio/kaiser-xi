<?php

namespace Tests\Feature\Domain;

use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlayerSeasonRegistrationActiveInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_active_registration_is_allowed_per_player_and_season(): void
    {
        $player = Player::factory()->create();
        $season = Season::factory()->create();
        $firstClub = SeasonClub::factory()->create(['season_id' => $season->id]);
        $secondClub = SeasonClub::factory()->create(['season_id' => $season->id]);
        $existing = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $firstClub->id]);

        $existing->update(['shirt_number' => 9]);
        $this->assertSame(9, $existing->refresh()->shirt_number);

        $this->expectException(ValidationException::class);
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $secondClub->id]);
    }

    public function test_released_or_inactive_history_does_not_block_a_new_active_registration(): void
    {
        foreach ([['released_on' => now()->toDateString()], ['is_active' => false]] as $historicalState) {
            $player = Player::factory()->create();
            $season = Season::factory()->create();
            PlayerSeasonRegistration::factory()->create($historicalState + ['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $season->id])->id]);
            PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $season->id])->id]);
        }

        $this->assertDatabaseCount('player_season_registrations', 4);
    }

    public function test_active_registration_in_a_different_season_is_allowed(): void
    {
        $player = Player::factory()->create();
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id]);
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id]);

        $this->assertDatabaseCount('player_season_registrations', 2);
    }
}
