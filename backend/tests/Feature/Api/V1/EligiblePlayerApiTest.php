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
use Database\Seeders\LeagueRoleSeeder;
use Database\Seeders\PlayerRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EligiblePlayerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            LeagueRoleSeeder::class,
            PlayerRoleSeeder::class,
        ]);
    }

    public function test_league_member_can_list_eligible_players(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $player = $this->eligiblePlayer($league, ['display_name' => 'Alpha Player']);

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league))
            ->assertOk()
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.name', 'Alpha Player')
            ->assertJsonPath('data.0.availability', 'available');
    }

    public function test_non_member_is_forbidden(): void
    {
        [$league] = $this->leagueWithMember();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson($this->endpoint($league))->assertForbidden();
    }

    public function test_only_players_registered_for_league_season_are_returned(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $eligible = $this->eligiblePlayer($league, ['display_name' => 'League Season Player']);
        $otherLeague = League::factory()->create();
        $ineligible = $this->eligiblePlayer($otherLeague, ['display_name' => 'Other Season Player']);

        Sanctum::actingAs($member);

        $ids = collect($this->getJson($this->endpoint($league))->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($eligible->id));
        $this->assertFalse($ids->contains($ineligible->id));
    }

    public function test_actively_assigned_players_in_same_league_are_excluded(): void
    {
        [$league, $member, $team] = $this->leagueWithMemberAndTeam();
        $player = $this->eligiblePlayer($league);
        $this->assign($league, $team, $player, $member, null);

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league))
            ->assertOk()
            ->assertJsonMissing(['id' => $player->id]);
    }

    public function test_released_players_become_eligible_again(): void
    {
        [$league, $member, $team] = $this->leagueWithMemberAndTeam();
        $player = $this->eligiblePlayer($league);
        $this->assign($league, $team, $player, $member, now());

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league))
            ->assertOk()
            ->assertJsonFragment(['id' => $player->id]);
    }

    public function test_player_assigned_in_another_league_remains_eligible(): void
    {
        [$league, $member] = $this->leagueWithMember();
        [$otherLeague, $otherUser, $otherTeam] = $this->leagueWithMemberAndTeam();
        $player = $this->eligiblePlayer($league);
        PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => SeasonClub::factory()->create(['season_id' => $otherLeague->season_id])->id,
        ]);
        $this->assign($otherLeague, $otherTeam, $player, $otherUser, null);

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league))
            ->assertOk()
            ->assertJsonFragment(['id' => $player->id]);
    }

    public function test_search_filters_by_player_name(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $match = $this->eligiblePlayer($league, ['display_name' => 'Needle Striker']);
        $this->eligiblePlayer($league, ['display_name' => 'Different Defender']);

        Sanctum::actingAs($member);

        $response = $this->getJson($this->endpoint($league, ['search' => 'Needle']))->assertOk();
        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_role_filtering_works(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $defender = $this->eligiblePlayer($league, ['display_name' => 'Role Match'], 'defender');
        $this->eligiblePlayer($league, ['display_name' => 'Role Miss'], 'forward');

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league, ['role' => 'defender']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $defender->id)
            ->assertJsonPath('data.0.role.key', 'defender');
    }

    public function test_club_filtering_works(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $realClub = RealClub::factory()->create(['name' => 'Filtered Club']);
        $seasonClub = SeasonClub::factory()->create(['season_id' => $league->season_id, 'real_club_id' => $realClub->id]);
        $match = $this->eligiblePlayer($league, ['display_name' => 'Club Match'], 'forward', $seasonClub);
        $this->eligiblePlayer($league, ['display_name' => 'Club Miss']);

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league, ['club_id' => $realClub->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.club.real_club_id', $realClub->id);
    }

    public function test_pagination_and_per_page_validation_work(): void
    {
        [$league, $member] = $this->leagueWithMember();
        foreach (['A Player', 'B Player', 'C Player'] as $name) {
            $this->eligiblePlayer($league, ['display_name' => $name]);
        }

        Sanctum::actingAs($member);

        $this->getJson($this->endpoint($league, ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson($this->endpoint($league, ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_club_filter_options_are_season_scoped_and_not_limited_to_the_current_page(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $lastClub = null;

        foreach (range(1, 12) as $number) {
            $lastClub = SeasonClub::factory()->create([
                'season_id' => $league->season_id,
                'display_name' => sprintf('Club %02d', $number),
            ]);
            $this->eligiblePlayer($league, ['display_name' => sprintf('Player %02d', $number)], 'forward', $lastClub);
        }

        $otherSeasonClub = SeasonClub::factory()->create(['display_name' => 'Other Season Club']);
        $inactiveClub = SeasonClub::factory()->create([
            'season_id' => $league->season_id,
            'display_name' => 'Inactive Club',
            'is_active' => false,
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson($this->endpoint($league, ['per_page' => 10]))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonCount(12, 'filter_options.clubs');

        $clubIds = collect($response->json('filter_options.clubs'))->pluck('id');
        $this->assertTrue($clubIds->contains($lastClub->id));
        $this->assertFalse($clubIds->contains($otherSeasonClub->id));
        $this->assertFalse($clubIds->contains($inactiveClub->id));
    }

    public function test_response_does_not_expose_internal_fields(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $this->eligiblePlayer($league);

        Sanctum::actingAs($member);

        $player = $this->getJson($this->endpoint($league))->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('registration_id', $player);
        $this->assertArrayNotHasKey('player_id', $player);
        $this->assertArrayNotHasKey('season_club_id', $player);
        $this->assertArrayNotHasKey('player_role_id', $player);
        $this->assertArrayNotHasKey('created_at', $player);
        $this->assertArrayNotHasKey('updated_at', $player);
    }

    public function test_query_results_have_deterministic_ordering(): void
    {
        [$league, $member] = $this->leagueWithMember();
        $zeta = $this->eligiblePlayer($league, ['display_name' => 'Zeta Player']);
        $alphaOne = $this->eligiblePlayer($league, ['display_name' => 'Alpha Player']);
        $alphaTwo = $this->eligiblePlayer($league, ['display_name' => 'Alpha Player']);

        Sanctum::actingAs($member);

        $ids = collect($this->getJson($this->endpoint($league))->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$alphaOne->id, $alphaTwo->id, $zeta->id], $ids);
    }

    private function endpoint(League $league, array $query = []): string
    {
        $url = "/api/v1/leagues/{$league->id}/eligible-players";

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    private function leagueWithMember(string $role = 'participant'): array
    {
        $league = League::factory()->create();
        $user = User::factory()->create();
        $this->attachMember($league, $user, $role);

        return [$league, $user];
    }

    private function leagueWithMemberAndTeam(): array
    {
        [$league, $user] = $this->leagueWithMember();
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $user)->create();

        return [$league, $user, $team];
    }

    private function attachMember(League $league, User $user, string $role): void
    {
        $league->users()->attach($user->id, [
            'league_role_id' => LeagueRole::query()->where('key', $role)->firstOrFail()->id,
            'joined_at' => now(),
        ]);
    }

    private function eligiblePlayer(League $league, array $attributes = [], string $role = 'forward', ?SeasonClub $seasonClub = null): Player
    {
        $player = Player::factory()->create($attributes);
        $roleId = PlayerRole::query()->where('key', $role)->firstOrFail()->id;
        PlayerSeasonRegistration::factory()->create([
            'player_id' => $player->id,
            'season_club_id' => ($seasonClub ?? SeasonClub::factory()->create(['season_id' => $league->season_id]))->id,
            'player_role_id' => $roleId,
            'quotation' => 42,
        ]);

        return $player;
    }

    private function assign(League $league, FantasyTeam $team, Player $player, User $user, mixed $releasedAt): void
    {
        FantasyTeamPlayer::factory()->create([
            'league_id' => $league->id,
            'fantasy_team_id' => $team->id,
            'player_id' => $player->id,
            'assigned_by_user_id' => $user->id,
            'assigned_at' => now(),
            'released_at' => $releasedAt,
        ]);
    }
}
