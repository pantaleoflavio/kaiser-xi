<?php

namespace Tests\Feature\Seeders;

use App\Models\League;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use Database\Seeders\DemoLeagueSeeder;
use Database\Seeders\DemoPlayersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;

#[Group('demo-integration')]
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
}
