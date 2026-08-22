<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoPlayersSeeder extends Seeder
{
    public const PLAYERS = [
        ['Alessandro Alba', 'goalkeeper', 0, 1, 18], ['Bruno Bruni', 'defender', 0, 2, 14],
        ['Carlo Cielo', 'defender', 0, 3, 16], ['Diego Doro', 'midfielder', 0, 4, 24],
        ['Enzo Estate', 'midfielder', 0, 5, 21], ['Fabio Fiume', 'forward', 0, 6, 32],
        ['Giorgio Gallo', 'goalkeeper', 1, 1, 17], ['Hector Lago', 'defender', 1, 2, 15],
        ['Ivan Isola', 'defender', 1, 3, 13], ['Luca Luna', 'midfielder', 1, 4, 27],
        ['Marco Monte', 'midfielder', 1, 5, 23], ['Nico Notte', 'forward', 1, 6, 35],
        ['Oscar Olivo', 'goalkeeper', 2, 1, 16], ['Paolo Porto', 'defender', 2, 2, 12],
        ['Quinto Quercia', 'defender', 2, 3, 14], ['Riccardo Riva', 'midfielder', 2, 4, 20],
        ['Stefano Sole', 'midfielder', 2, 5, 26], ['Tomas Torre', 'forward', 2, 6, 30],
        ['Umberto Ulivo', 'goalkeeper', 3, 1, 19], ['Vito Valle', 'defender', 3, 2, 17],
        ['Walter Vento', 'defender', 3, 3, 15], ['Xavier Xilo', 'midfielder', 3, 4, 22],
        ['Yuri Ypsilon', 'midfielder', 3, 5, 25], ['Zeno Zaffiro', 'forward', 3, 6, 34],
    ];

    public function run(): void
    {
        $season = Season::query()->where('name', DemoLeagueSeeder::SEASON_NAME)
            ->whereHas('realCompetition', fn ($query) => $query->where('code', 'serie_a'))
            ->firstOrFail();
        $clubs = collect(DemoLeagueSeeder::CLUBS)->map(
            fn (array $club) => SeasonClub::query()
                ->where('season_id', $season->id)
                ->whereHas('realClub', fn ($query) => $query->where('slug', $club[2]))
                ->firstOrFail()
        );
        $roles = PlayerRole::query()->pluck('id', 'key');

        foreach (self::PLAYERS as [$displayName, $role, $clubIndex, $shirtNumber, $quotation]) {
            [$firstName, $lastName] = explode(' ', $displayName, 2);
            $slug = 'demo-'.Str::slug($displayName);
            $player = Player::query()->updateOrCreate(
                ['slug' => $slug],
                ['first_name' => $firstName, 'last_name' => $lastName, 'display_name' => $displayName, 'is_active' => true],
            );

            PlayerSeasonRegistration::query()->updateOrCreate(
                ['player_id' => $player->id, 'season_club_id' => $clubs[$clubIndex]->id],
                [
                    'player_role_id' => $roles[$role],
                    'shirt_number' => $shirtNumber,
                    'quotation' => $quotation,
                    'is_active' => true,
                    'registered_at' => '2025-08-01 00:00:00',
                    'released_at' => null,
                ],
            );
        }
    }
}
