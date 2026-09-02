<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\SeasonClub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaguePlayerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_players_and_ownership_are_scoped_to_the_league(): void
    {
        [$league, $member] = $this->leagueWithMember();
        [$otherLeague, $otherMember] = $this->leagueWithMember();
        $free = $this->register($league, 'Free Player');
        $owned = $this->register($league, 'Owned Player');
        $ownedElsewhere = $this->register($league, 'Elsewhere Player');
        $wrongSeason = $this->register($otherLeague, 'Wrong Season');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $member)->create(['name' => 'League Team']);
        $otherTeam = FantasyTeam::factory()->forLeagueAndUser($otherLeague, $otherMember)->create();
        $this->assign($league, $team, $owned, $member);
        $this->assign($otherLeague, $otherTeam, $ownedElsewhere, $otherMember);

        Sanctum::actingAs($member);
        $response = $this->getJson("/api/v1/leagues/{$league->id}/players")->assertOk();
        $players = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($players->has($free->id));
        $this->assertTrue($players[$free->id]['is_free_agent']);
        $this->assertSame('League Team', $players[$owned->id]['fantasy_team']['name']);
        $this->assertTrue($players[$ownedElsewhere->id]['is_free_agent']);
        $this->assertFalse($players->has($wrongSeason->id));
    }

    public function test_combined_filters_search_and_pagination_work(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $club = RealClub::factory()->create(['name' => 'Bayern']);
        $seasonClub = SeasonClub::factory()->create(['season_id' => $league->season_id, 'real_club_id' => $club->id]);
        $match = $this->register($league, 'Needle Forward', 'forward', $seasonClub);
        $this->register($league, 'Other Forward');

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/leagues/{$league->id}/players?search=needle&club_id={$club->id}&role=forward&assignment_state=unassigned&per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_released_assignment_is_free_and_non_member_is_forbidden(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $player = $this->register($league, 'Released Player');
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $member)->create();
        $this->assign($league, $team, $player, $member, now());

        Sanctum::actingAs($member);
        $this->getJson("/api/v1/leagues/{$league->id}/players")
            ->assertOk()->assertJsonPath('data.0.is_free_agent', true);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/leagues/{$league->id}/players")->assertForbidden();
    }

    private function leagueWithMember(): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $league->users()->attach($user, [
            'league_role_id' => LeagueRole::query()->where('key', 'participant')->firstOrFail()->id,
            'joined_at' => now(),
        ]);

        return [$league, $user];
    }

    private function register(League $league, string $name, string $role = 'forward', ?SeasonClub $club = null): Player
    {
        $player = Player::factory()->create(['display_name' => $name]);
        PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => ($club ?? SeasonClub::factory()->create(['season_id' => $league->season_id]))->id,
            'player_role_id' => PlayerRole::query()->where('key', $role)->firstOrFail()->id,
        ]);

        return $player;
    }

    private function assign(League $league, FantasyTeam $team, Player $player, User $user, mixed $releasedAt = null): void
    {
        FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $user->id,
            'released_at' => $releasedAt,
        ]);
    }
}
