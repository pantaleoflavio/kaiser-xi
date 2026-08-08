<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\RealClub;
use App\Models\SeasonClub;
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

        $league = League::query()
            ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
            ->firstOrFail();

        $this->assertSame(
            1,
            League::query()
                ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
                ->count()
        );

        $this->assertSame(9, $league->memberships()->count());
        $this->assertSame(9, $league->fantasyTeams()->count());

        $expectedSettingKeys = [
            LeagueSetting::INITIAL_BUDGET,
            LeagueSetting::RELEASE_REFUND_PERCENTAGE,
            LeagueSetting::MAX_ROSTER_PLAYERS,
            LeagueSetting::ROSTER_ROLE_LIMITS,
            LeagueSetting::ALLOWED_FORMATION_MODULE_NAMES,
            LeagueSetting::BENCH_SIZE,
            LeagueSetting::BENCH_ROLE_LIMITS,
            LeagueSetting::MAX_SUBSTITUTIONS,
            LeagueSetting::SUBSTITUTION_ORDER_MODE,
            LeagueSetting::ALLOW_FORMATION_CHANGE_ON_SUBSTITUTION,
            LeagueSetting::CAPTAIN_ENABLED,
        ];

        $this->assertSame(
            collect($expectedSettingKeys)
                ->sort()
                ->values()
                ->all(),
            $league->settings()
                ->pluck('key')
                ->sort()
                ->values()
                ->all()
        );

        foreach ($expectedSettingKeys as $key) {
            $this->assertSame(
                1,
                $league->settings()
                    ->where('key', $key)
                    ->count()
            );
        }

        $this->assertSame(500, $league->initialFantasyBudget());
        $this->assertSame(50, $league->releaseRefundPercentage());
        $this->assertSame(25, $league->maxRosterPlayers());

        $this->assertSame(
            LeagueSetting::DEFAULT_ROSTER_ROLE_LIMITS,
            $league->rosterRoleLimits()
        );

        foreach (DemoLeagueSeeder::MEMBERS as $email => [$role]) {
            $user = User::query()
                ->where('email', $email)
                ->firstOrFail();

            $membership = $league->memberships()
                ->with('role')
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->assertDatabaseHas('league_user', [
                'league_id' => $league->id,
                'user_id' => $user->id,
                'league_role_id' => $membership->league_role_id,
            ]);

            $this->assertSame($role, $membership->role->key);
        }

        $this->assertSame(
            7,
            FantasyTeamPlayer::query()
                ->where('league_id', $league->id)
                ->active()
                ->count()
        );

        $this->assertSame(
            1,
            FantasyTeamPlayer::query()
                ->where('league_id', $league->id)
                ->whereNotNull('released_at')
                ->count()
        );

        foreach (
            FantasyTeam::query()
                ->where('league_id', $league->id)
                ->get() as $team
        ) {
            $this->assertLessThanOrEqual(
                $league->initialFantasyBudget(),
                (int) $team->activePlayerAssignments()
                    ->sum('purchase_price')
            );

            $this->assertGreaterThanOrEqual(
                0,
                (int) $team->remaining_budget
            );
        }

        $releasedPlayer = Player::query()
            ->where('slug', 'demo-carlo-cielo')
            ->firstOrFail();

        $eligiblePlayerQuery = app(
            \App\Services\FantasyTeam\EligiblePlayerQueryService::class
        )->query($league);

        $this->assertTrue(
            (clone $eligiblePlayerQuery)
                ->where('player_id', $releasedPlayer->id)
                ->exists()
        );

        $this->assertGreaterThan(
            0,
            (clone $eligiblePlayerQuery)->count()
        );
    }

    public function test_seeded_players_can_be_filtered_by_role_and_club_through_the_endpoint(): void
    {
        $this->seed(DemoEnvironmentSeeder::class);

        $league = League::query()
            ->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)
            ->firstOrFail();

        $member = User::query()
            ->where('email', 'demo.participant1@example.com')
            ->firstOrFail();

        $realClub = RealClub::query()
            ->where('slug', 'demo-dolomiti-athletic')
            ->firstOrFail();

        $seasonClub = SeasonClub::query()
            ->where('season_id', $league->season_id)
            ->where('real_club_id', $realClub->id)
            ->firstOrFail();

        $response = $this->actingAs($member)
            ->getJson(
                "/api/v1/leagues/{$league->id}/eligible-players"
                . '?role=midfielder'
                . "&club_id={$seasonClub->id}"
                . '&per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $eligiblePlayer) {
            $this->assertSame(
                'midfielder',
                $eligiblePlayer['role']['key']
            );

            $this->assertSame(
                $seasonClub->id,
                $eligiblePlayer['club']['id']
            );

            $this->assertSame(
                $realClub->id,
                $eligiblePlayer['club']['real_club_id']
            );

            $this->assertSame(
                'available',
                $eligiblePlayer['availability']
            );
        }
    }
}