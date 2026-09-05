<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Player;
use App\Models\Season;
use App\Models\Matchday;
use App\Models\RealMatch;
use App\Models\PlayerRole;
use App\Models\SeasonClub;
use App\Models\PlayerScore;
use Laravel\Sanctum\Sanctum;
use App\Enums\PlayerScoreStatus;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlayerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_registration_aggregates_history_and_reliable_opponent(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $season = Season::factory()->create();
        $club = SeasonClub::factory()->create(['season_id' => $season->id, 'display_name' => 'Kaisers']);
        $opponent = SeasonClub::factory()->create(['season_id' => $season->id, 'display_name' => 'Rivals']);
        $role = PlayerRole::query()->create(['key' => 'goalkeeper', 'label' => 'Goalkeeper', 'sort_order' => 1]);
        $player = Player::factory()->create(['display_name' => 'Max Keeper']);
        $registration = PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => $club->id,
            'player_role_id' => $role->id,
            'shirt_number' => 1,
        ]);
        $played = Matchday::factory()->create(['season_id' => $season->id, 'number' => 1]);
        $pending = Matchday::factory()->create(['season_id' => $season->id, 'number' => 2]);
        $dnp = Matchday::factory()->create(['season_id' => $season->id, 'number' => 3]);
        Matchday::factory()->create(['season_id' => $season->id, 'number' => 4]);
        RealMatch::factory()->create(['matchday_id' => $played->id, 'home_season_club_id' => $club->id, 'away_season_club_id' => $opponent->id]);
        PlayerScore::factory()->create([
            'player_season_registration_id' => $registration->id,
            'matchday_id' => $played->id,
            'base_rating' => 7.25,
            'goals' => 1,
            'assists' => 2,
            'yellow_cards' => 1,
            'penalties_saved' => 1,
            'goals_conceded' => 1,
            'clean_sheet' => true,
            'is_captain' => true,
            'status' => PlayerScoreStatus::Confirmed,
        ]);
        PlayerScore::factory()->pending()->create(['player_season_registration_id' => $registration->id, 'matchday_id' => $pending->id, 'base_rating' => 9, 'goals' => 9]);
        PlayerScore::factory()->didNotPlay()->create(['player_season_registration_id' => $registration->id, 'matchday_id' => $dnp->id]);

        $this->getJson("/api/v1/players/{$player->id}?season_id={$season->id}")
            ->assertOk()
            ->assertJsonPath('data.registration.club.name', 'Kaisers')
            ->assertJsonPath('data.registration.role.key', 'goalkeeper')
            ->assertJsonPath('data.statistics.appearances', 1)
            ->assertJsonPath('data.statistics.average_rating', '7.25')
            ->assertJsonPath('data.statistics.goals', 1)
            ->assertJsonPath('data.statistics.assists', 2)
            ->assertJsonPath('data.statistics.penalties_saved', 1)
            ->assertJsonPath('data.statistics.goals_conceded', 1)
            ->assertJsonPath('data.statistics.clean_sheets', 1)
            ->assertJsonPath('data.statistics.captain_appearances', 1)
            ->assertJsonPath('data.matchdays.0.status', 'played')
            ->assertJsonPath('data.matchdays.0.opponent.name', 'Rivals')
            ->assertJsonPath('data.matchdays.0.venue', 'home')
            ->assertJsonPath('data.matchdays.1.status', 'pending')
            ->assertJsonPath('data.matchdays.2.status', 'did_not_play')
            ->assertJsonPath('data.matchdays.3.status', 'no_data');
    }

    public function test_no_performances_returns_null_average_and_ambiguous_opponent_is_omitted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$player, $season, $registration] = $this->registration();
        $day = Matchday::factory()->create(['season_id' => $season->id]);
        $opponentOne = SeasonClub::factory()->create(['season_id' => $season->id]);
        $opponentTwo = SeasonClub::factory()->create(['season_id' => $season->id]);
        RealMatch::factory()->create(['matchday_id' => $day->id, 'home_season_club_id' => $registration->season_club_id, 'away_season_club_id' => $opponentOne->id]);
        RealMatch::factory()->create(['matchday_id' => $day->id, 'home_season_club_id' => $registration->season_club_id, 'away_season_club_id' => $opponentTwo->id]);
        PlayerScore::factory()->pending()->create(['player_season_registration_id' => $registration->id, 'matchday_id' => $day->id]);

        $this->getJson("/api/v1/players/{$player->id}?season_id={$season->id}")
            ->assertOk()->assertJsonPath('data.statistics.appearances', 0)
            ->assertJsonPath('data.statistics.average_rating', null)
            ->assertJsonPath('data.matchdays.0.opponent', null);
    }

    public function test_profile_requires_auth_valid_player_and_valid_season(): void
    {
        $player = Player::factory()->create();
        $season = Season::factory()->create();
        $this->getJson("/api/v1/players/{$player->id}?season_id={$season->id}")->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/players/999999?season_id={$season->id}")->assertNotFound();
        $this->getJson("/api/v1/players/{$player->id}?season_id=999999")->assertUnprocessable();
    }

    /** @return array{Player, Season, PlayerSeasonRegistration} */
    private function registration(): array
    {
        $season = Season::factory()->create();
        $club = SeasonClub::factory()->create(['season_id' => $season->id]);
        $player = Player::factory()->create();
        $registration = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => $club->id]);
        return [$player, $season, $registration];
    }
}
