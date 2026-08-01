<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\User;
use Database\Seeders\DemoEnvironmentSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoEnvironmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_environment_is_complete_and_idempotent(): void
    {
        $this->seed(DemoEnvironmentSeeder::class);
        $this->seed(DemoEnvironmentSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('real_clubs', 4);
        $this->assertDatabaseCount('season_clubs', 4);
        $this->assertDatabaseCount('players', 24);
        $this->assertDatabaseCount('player_season_registrations', 24);

        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $this->assertSame(1, League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->count());
        $this->assertSame(9, $league->memberships()->count());
        $this->assertSame(9, $league->fantasyTeams()->count());
        $this->assertSame(2, $league->settings()->count());
        $this->assertSame(1, $league->settings()->where('key', LeagueSetting::INITIAL_BUDGET)->count());
        $this->assertSame(500, $league->initialFantasyBudget());
        $this->assertSame(50, $league->releaseRefundPercentage());

        foreach (DemoLeagueSeeder::MEMBERS as $email => [$role]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertDatabaseHas('league_user', [
                'league_id' => $league->id,
                'user_id' => $user->id,
                'league_role_id' => $league->memberships()->where('user_id', $user->id)->firstOrFail()->role->id,
            ]);
            $this->assertSame($role, $league->memberships()->where('user_id', $user->id)->firstOrFail()->role->key);
        }

        $this->assertSame(7, FantasyTeamPlayer::query()->where('league_id', $league->id)->active()->count());
        $this->assertSame(1, FantasyTeamPlayer::query()->where('league_id', $league->id)->whereNotNull('released_at')->count());
        foreach (FantasyTeam::query()->where('league_id', $league->id)->get() as $team) {
            $this->assertLessThanOrEqual(
                $league->initialFantasyBudget(),
                (int) $team->activePlayerAssignments()->sum('purchase_price'),
            );
            $this->assertGreaterThanOrEqual(0, (int) $team->remaining_budget);
        }

        $released = Player::query()->where('slug', 'demo-carlo-cielo')->firstOrFail();
        $this->assertTrue(app(\App\Services\FantasyTeam\EligiblePlayerQueryService::class)
            ->query($league)->where('player_id', $released->id)->exists());
        $this->assertGreaterThan(0, app(\App\Services\FantasyTeam\EligiblePlayerQueryService::class)->query($league)->count());
    }

    public function test_seeded_players_can_be_filtered_by_role_and_club_through_the_endpoint(): void
    {
        $this->seed(DemoEnvironmentSeeder::class);
        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $member = User::query()->where('email', 'demo.participant1@example.com')->firstOrFail();
        $club = RealClub::query()->where('slug', 'demo-dolomiti-athletic')->firstOrFail();

        $response = $this->actingAs($member)->getJson(
            "/api/v1/leagues/{$league->id}/eligible-players?role=midfielder&club_id={$club->id}&per_page=2"
        )->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $registration) {
            $seeded = PlayerSeasonRegistration::query()->with(['playerRole', 'seasonClub'])
                ->whereHas('player', fn ($query) => $query->where('display_name', $registration['name']))
                ->firstOrFail();
            $this->assertSame('midfielder', $seeded->playerRole->key);
            $this->assertSame($club->id, $seeded->seasonClub->real_club_id);
        }
    }
}