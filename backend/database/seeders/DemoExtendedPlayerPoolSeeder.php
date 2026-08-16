<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Seeder;

class DemoExtendedPlayerPoolSeeder extends Seeder
{
    /**
     * Capacity beyond the named pool. Final totals are GK 18, DEF 47, MID 47, FWD 28:
     * nine Classic 2/5/5/3 profiles, the commissioner's extra DEF/FWD, released Carlo,
     * and two reserved midfield free agents. The six-team H2H 2/5/5/3 profile is smaller.
     */
    public const ROSTER_POOL_COUNTS = [
        'goalkeeper' => 14,
        'defender' => 39,
        'midfielder' => 37,
        'forward' => 24,
    ];

    public const FREE_AGENTS = [
        ['Demo Free Agent Midfielder One', 'demo-free-agent-midfielder-one', 41],
        ['Demo Free Agent Midfielder Two', 'demo-free-agent-midfielder-two', 42],
    ];

    public function run(): void
    {
        $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
        $clubs = SeasonClub::query()->where('season_id', $season->id)->with('realClub')->orderBy('id')->get();
        $roles = PlayerRole::query()->pluck('id', 'key');

        foreach (self::ROSTER_POOL_COUNTS as $role => $count) {
            for ($number = 1; $number <= $count; $number++) {
                $label = ucfirst($role) . ' ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                $slug = 'demo-arena-' . str_replace('_', '-', $role) . '-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                $player = Player::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'first_name' => 'Arena',
                        'last_name' => $label,
                        'display_name' => 'Arena ' . $label,
                        'is_active' => true,
                    ],
                );
                $club = $clubs[($number - 1) % $clubs->count()];

                PlayerSeasonRegistration::query()->updateOrCreate(
                    ['player_id' => $player->id, 'season_club_id' => $club->id],
                    [
                        'player_role_id' => $roles[$role],
                        'shirt_number' => $number,
                        'quotation' => 10,
                        'is_active' => true,
                        'registered_at' => '2025-08-01 00:00:00',
                        'released_at' => null,
                    ],
                );
            }
        }
        $dolomiti = $clubs->first(fn(SeasonClub $club): bool => $club->realClub->slug === 'demo-dolomiti-athletic');
        foreach (self::FREE_AGENTS as [$name, $slug, $shirtNumber]) {
            $player = Player::query()->updateOrCreate(
                ['slug' => $slug],
                ['first_name' => 'Demo', 'last_name' => (string) str($name)->after('Demo '), 'display_name' => $name, 'is_active' => true],
            );
            PlayerSeasonRegistration::query()->updateOrCreate(
                ['player_id' => $player->id, 'season_club_id' => $dolomiti->id],
                [
                    'player_role_id' => $roles['midfielder'],
                    'shirt_number' => $shirtNumber,
                    'quotation' => 10,
                    'is_active' => true,
                    'registered_at' => '2025-08-01 00:00:00',
                    'released_at' => null,
                ],
            );
        }
    }
}
