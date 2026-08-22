<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\SeasonClub;
use App\Models\User;
use Database\Seeders\DemoExtendedPlayerPoolSeeder;
use Database\Seeders\DemoFantasyRostersSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\DemoPlayersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;

class DemoPlayerPoolSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoFoundation;

    public function test_shared_pool_has_required_role_capacity_and_explicit_free_agents(): void
    {
        $this->seedDemoFoundation();

        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $roleCounts = PlayerSeasonRegistration::query()->activeForSeason($league->season_id)
            ->join('player_roles', 'player_roles.id', '=', 'player_season_registrations.player_role_id')
            ->selectRaw('player_roles.key, count(*) as aggregate')->groupBy('player_roles.key')
            ->pluck('aggregate', 'player_roles.key')->map(fn($count): int => (int) $count);

        $this->assertSame(18, $roleCounts['goalkeeper']);
        $this->assertSame(47, $roleCounts['defender']);
        $this->assertSame(47, $roleCounts['midfielder']);
        $this->assertSame(28, $roleCounts['forward']);
        $this->assertSame(count(DemoPlayersSeeder::PLAYERS), Player::query()->where('slug', 'like', 'demo-%')
            ->where('slug', 'not like', 'demo-arena-%')->where('slug', 'not like', 'demo-free-agent-%')->count());
        $this->assertSame(Player::query()->count(), PlayerSeasonRegistration::query()->count());
    }

    public function test_eligible_player_endpoint_uses_reserved_dolomiti_midfielders_without_heavy_results_seeders(): void
    {
        $this->seedDemoFoundation();
        $this->seed(DemoFantasyRostersSeeder::class);

        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $member = User::query()->where('email', 'demo.participant1@example.com')->firstOrFail();
        $realClub = RealClub::query()->where('slug', 'demo-dolomiti-athletic')->firstOrFail();
        $seasonClub = SeasonClub::query()->where('season_id', $league->season_id)
            ->where('real_club_id', $realClub->id)->firstOrFail();
        $reservedIds = Player::query()->whereIn('slug', collect(DemoExtendedPlayerPoolSeeder::FREE_AGENTS)->pluck(1))->pluck('id');

        $this->assertSame(0, FantasyTeamPlayer::query()->active()->where('league_id', $league->id)
            ->whereIn('player_id', $reservedIds)->count());

        $response = $this->actingAs($member)->getJson(
            "/api/v1/leagues/{$league->id}/eligible-players?role=midfielder&club_id={$seasonClub->id}&per_page=100"
        )->assertOk();

        $returnedIds = collect($response->json('data'))
            ->pluck('id')
            ->values();

        $returnedReservedIds = $returnedIds
            ->intersect($reservedIds)
            ->values();

        $this->assertEqualsCanonicalizing(
            $reservedIds->values()->all(),
            $returnedReservedIds->all(),
        );
        foreach ($response->json('data') as $eligiblePlayer) {
            $this->assertSame('midfielder', $eligiblePlayer['role']['key']);
            $this->assertSame($seasonClub->id, $eligiblePlayer['club']['id']);
            $this->assertSame($realClub->id, $eligiblePlayer['club']['real_club_id']);
            $this->assertSame('available', $eligiblePlayer['availability']);
        }
    }
}
