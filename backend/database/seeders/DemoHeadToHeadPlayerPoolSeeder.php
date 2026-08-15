<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Seeder;

class DemoHeadToHeadPlayerPoolSeeder extends Seeder
{
    /** Additional players needed after the shared demo pool is reused. */
    private const ROLE_COUNTS = [
        'goalkeeper' => 8,
        'defender' => 22,
        'midfielder' => 22,
        'forward' => 14,
    ];

    public function run(): void
    {
        $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)->firstOrFail();
        $clubs = SeasonClub::query()->where('season_id', $season->id)->orderBy('id')->get();
        $roles = PlayerRole::query()->pluck('id', 'key');

        foreach (self::ROLE_COUNTS as $role => $count) {
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
    }
}
