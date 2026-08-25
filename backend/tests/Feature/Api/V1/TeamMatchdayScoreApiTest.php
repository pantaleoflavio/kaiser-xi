<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\User;
use App\Models\League;
use App\Models\Player;
use App\Models\Matchday;
use App\Models\Formation;
use App\Models\LeagueRole;
use App\Models\PlayerRole;
use App\Models\SeasonClub;
use App\Models\FantasyTeam;
use App\Models\PlayerScore;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;
use App\Models\FormationModule;
use App\Models\FormationPlayer;
use App\Models\FantasyTeamPlayer;
use App\Models\TeamMatchdayScore;
use App\Models\PlayerSeasonRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamMatchdayScoreApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_member_reads_authoritative_result_and_substitution_context_after_deadline(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $this->seedReferenceData();
        $league = League::factory()->create();
        $member = User::factory()->create();
        $league->users()->attach($member, ['league_role_id' => LeagueRole::where('key', 'participant')->firstOrFail()->id, 'joined_at' => now()]);
        $team = FantasyTeam::factory()->create(['league_id' => $league->id]);
        $league->users()->attach($team->user, ['league_role_id' => LeagueRole::where('key', 'participant')->firstOrFail()->id, 'joined_at' => now()]);
        $matchday = Matchday::factory()->create(['season_id' => $league->season_id, 'starts_at' => now()->addHour()]);
        $formation = Formation::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
            'formation_module_id' => FormationModule::where('name', '4-3-3')->firstOrFail()->id,
            'is_confirmed' => true,
            'submitted_at' => now()->subHour(),
        ]);
        $role = PlayerRole::where('key', 'defender')->firstOrFail();
        $outgoing = $this->formationPlayer($formation, $role, 'starter', 1);
        $incoming = $this->formationPlayer($formation, $role, 'bench', 1);
        $unused = $this->formationPlayer($formation, $role, 'bench', 2);
        $playerScore = $this->score($incoming->player, $matchday, $league, true);
        $aggregate = TeamMatchdayScore::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'matchday_id' => $matchday->id,
            'formation_id' => $formation->id,
            'points' => '7.00',
            'base_points' => '6.50',
            'status' => 'calculated',
        ]);
        $aggregate->details()->createMany([
            ['player_id' => $outgoing->player_id, 'points' => 0, 'was_starter' => true, 'was_bench' => false, 'was_used_as_substitute' => false],
            ['player_id' => $incoming->player_id, 'player_score_id' => $playerScore->id, 'replaced_player_id' => $outgoing->player_id, 'points' => 7, 'was_starter' => false, 'was_bench' => true, 'was_used_as_substitute' => true],
            ['player_id' => $unused->player_id, 'points' => 0, 'was_starter' => false, 'was_bench' => true, 'was_used_as_substitute' => false],
        ]);

        Sanctum::actingAs($member);
        $url = "/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/fantasy-teams/{$team->id}/score";
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($team->user);
        $this->getJson($url)->assertForbidden();

        Carbon::setTestNow($matchday->starts_at);
        Sanctum::actingAs($team->user);
        $this->getJson($url)->assertOk();

        Sanctum::actingAs($member);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.result.points', '7.00')
            ->assertJsonPath('data.formation.players.0.replaced_by_player.id', $incoming->player_id)
            ->assertJsonPath('data.formation.players.1.used_as_substitute', true)
            ->assertJsonPath('data.formation.players.1.player_score.base_rating', '6.50')
            ->assertJsonMissingPath('data.formation.players.1.player_score.final_score')
            ->assertJsonPath('data.formation.players.1.player_score.is_real_captain', true)
            ->assertJsonPath('data.formation.players.2.effective_contribution', '0.00');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs($member);
        $otherLeague = League::factory()->create(['season_id' => $league->season_id]);
        $this->getJson("/api/v1/leagues/{$otherLeague->id}/matchdays/{$matchday->id}/fantasy-teams/{$team->id}/score")
            ->assertNotFound();

        $otherMatchday = Matchday::factory()->create(['starts_at' => now()->subHour()]);
        $this->getJson("/api/v1/leagues/{$league->id}/matchdays/{$otherMatchday->id}/fantasy-teams/{$team->id}/score")
            ->assertNotFound();
    }

    private function formationPlayer(Formation $formation, PlayerRole $role, string $slot, int $order): FormationPlayer
    {
        $player = Player::factory()->create();
        $assignment = FantasyTeamPlayer::factory()->create(['league_id' => $formation->league_id, 'fantasy_team_id' => $formation->fantasy_team_id, 'player_id' => $player->id]);

        return FormationPlayer::factory()->create(['formation_id' => $formation->id, 'fantasy_team_player_id' => $assignment->id, 'player_id' => $player->id, 'player_role_id' => $role->id, 'slot_type' => $slot, 'position_index' => $order]);
    }

    private function score(Player $player, Matchday $matchday, League $league, bool $captain): PlayerScore
    {
        $registration = PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id]);

        return PlayerScore::factory()->confirmed(6.5)->state(['is_captain' => $captain])->create(['player_season_registration_id' => $registration->id, 'matchday_id' => $matchday->id]);
    }
}
