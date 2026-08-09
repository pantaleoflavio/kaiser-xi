<?php

namespace Database\Seeders;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\League;
use App\Models\Player;
use App\Models\User;
use App\Services\FantasyTeam\FantasyRosterService;
use Illuminate\Database\Seeder;

class DemoFantasyRostersSeeder extends Seeder
{
    private const ACTIVE_ASSIGNMENTS = [
        'commissioner-fc' => [
            'demo-alessandro-alba' => 18,
            'demo-bruno-bruni' => 14,
            'demo-ivan-isola' => 13,
            'demo-paolo-porto' => 12,
            'demo-quinto-quercia' => 14,
            'demo-vito-valle' => 17,
            'demo-walter-vento' => 15,
            'demo-diego-doro' => 24,
            'demo-enzo-estate' => 21,
            'demo-luca-luna' => 27,
            'demo-marco-monte' => 23,
            'demo-fabio-fiume' => 32,
            'demo-nico-notte' => 35,
            'demo-tomas-torre' => 30,
            'demo-umberto-ulivo' => 19,
            'demo-zeno-zaffiro' => 34,
        ],
        'co-commissioner-united' => [
            'demo-giorgio-gallo' => 17,
            'demo-hector-lago' => 15,
        ],
        'participant-1-fc' => [
            'demo-oscar-olivo' => 16,
        ],
    ];

    public function __construct(private FantasyRosterService $rosters) {}

    public function run(): void
    {
        $league = League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->firstOrFail();
        $commissioner = User::query()->where('email', 'demo.commissioner@example.com')->firstOrFail();

        foreach (self::ACTIVE_ASSIGNMENTS as $teamSlug => $assignments) {
            $team = FantasyTeam::query()->where('league_id', $league->id)->where('slug', $teamSlug)->firstOrFail();
            foreach ($assignments as $playerSlug => $price) {
                $player = Player::query()->where('slug', $playerSlug)->firstOrFail();
                if (! FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('player_id', $player->id)->exists()) {
                    $this->rosters->assign($league, $team, $player, $commissioner, $price);
                }
            }
        }

        $releasedPlayer = Player::query()->where('slug', 'demo-carlo-cielo')->firstOrFail();
        $team = FantasyTeam::query()->where('league_id', $league->id)->where('slug', 'commissioner-fc')->firstOrFail();
        $historical = FantasyTeamPlayer::query()
            ->where('league_id', $league->id)
            ->where('fantasy_team_id', $team->id)
            ->where('player_id', $releasedPlayer->id)
            ->whereNotNull('released_at')
            ->exists();

        if (! $historical) {
            if (! FantasyTeamPlayer::query()->active()->where('league_id', $league->id)->where('player_id', $releasedPlayer->id)->exists()) {
                $this->rosters->assign($league, $team, $releasedPlayer, $commissioner, 16);
            }
            $this->rosters->release($league, $team, $releasedPlayer, $commissioner);
        }
    }
}
