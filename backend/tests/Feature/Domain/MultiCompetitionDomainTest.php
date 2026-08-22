<?php

namespace Tests\Feature\Domain;

use App\Models\League;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\RealCompetition;
use App\Models\RealMatch;
use App\Models\Season;
use App\Models\SeasonClub;
use Database\Seeders\RealCompetitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCompetitionDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_competitions_are_seeded_idempotently(): void
    {
        $this->seed(RealCompetitionSeeder::class);
        $this->seed(RealCompetitionSeeder::class);

        $this->assertSame(7, RealCompetition::query()->count());
        $this->assertSame(
            ['bundesliga', 'champions_league', 'custom_euroleague', 'la_liga', 'ligue_1', 'premier_league', 'serie_a'],
            RealCompetition::query()->orderBy('code')->pluck('code')->all(),
        );
    }

    public function test_season_belongs_to_a_real_competition_and_league_resolves_it_through_season(): void
    {
        $this->seed(RealCompetitionSeeder::class);
        $competition = RealCompetition::factory()->create();
        $season = Season::factory()->create(['real_competition_id' => $competition->id]);
        $league = League::factory()->create(['season_id' => $season->id]);

        $this->assertTrue($season->realCompetition->is($competition));
        $this->assertTrue($league->season->realCompetition->is($competition));
        $this->assertTrue($season->leagues->contains($league));
    }

    public function test_global_real_club_can_participate_in_multiple_seasons_and_competitions(): void
    {
        $club = RealClub::factory()->create();
        $domesticSeason = Season::factory()->create();
        $internationalSeason = Season::factory()->create();
        $domesticParticipation = SeasonClub::factory()->create(['season_id' => $domesticSeason->id, 'real_club_id' => $club->id]);
        $internationalParticipation = SeasonClub::factory()->create(['season_id' => $internationalSeason->id, 'real_club_id' => $club->id]);

        $this->assertCount(2, $club->seasonClubs);
        $this->assertTrue($domesticParticipation->realClub->is($club));
        $this->assertTrue($internationalParticipation->realClub->is($club));
        $this->assertNotSame($domesticSeason->real_competition_id, $internationalSeason->real_competition_id);
    }

    public function test_global_player_can_have_club_registrations_with_different_quotations(): void
    {
        $player = Player::factory()->create();
        $firstRegistration = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'quotation' => 25.50]);
        $secondRegistration = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'quotation' => 42.75]);

        $this->assertCount(2, $player->playerSeasonRegistrations);
        $this->assertTrue($firstRegistration->player->is($player));
        $this->assertNotNull($firstRegistration->seasonClub->season);
        $this->assertNotNull($firstRegistration->seasonClub->realClub);
        $this->assertSame('25.50', $firstRegistration->quotation);
        $this->assertSame('42.75', $secondRegistration->quotation);
    }

    public function test_real_match_references_home_and_away_season_clubs(): void
    {
        $matchday = Matchday::factory()->create();

        $home = SeasonClub::factory()->create([
            'season_id' => $matchday->season_id,
        ]);

        $away = SeasonClub::factory()->create([
            'season_id' => $matchday->season_id,
        ]);

        $match = RealMatch::factory()->create([
            'matchday_id' => $matchday->id,
            'home_season_club_id' => $home->id,
            'away_season_club_id' => $away->id,
        ]);

        $this->assertTrue($match->homeSeasonClub->is($home));
        $this->assertTrue($match->awaySeasonClub->is($away));
        $this->assertTrue($home->homeRealMatches->contains($match));
        $this->assertTrue($away->awayRealMatches->contains($match));
    }

    public function test_player_score_references_player_season_registration(): void
    {
        $matchday = Matchday::factory()->create();
        $registration = PlayerSeasonRegistration::factory()->create(['season_club_id' => SeasonClub::factory()->create(['season_id' => $matchday->season_id])->id]);
        $score = PlayerScore::factory()->create([
            'matchday_id' => $matchday->id,
            'player_season_registration_id' => $registration->id,
        ]);

        $this->assertTrue($score->playerSeasonRegistration->is($registration));
        $this->assertTrue($registration->playerScores->contains($score));
    }
}
