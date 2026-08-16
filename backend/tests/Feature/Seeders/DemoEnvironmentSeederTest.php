<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\Player;
use App\Models\PlayerScore;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use Database\Seeders\DemoEnvironmentSeeder;
use Database\Seeders\DemoExtendedPlayerPoolSeeder;
use Database\Seeders\DemoHeadToHeadLeagueSeeder;
use Database\Seeders\DemoHeadToHeadResultsSeeder;
use Database\Seeders\DemoLeagueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoEnvironmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_environment_is_complete_and_idempotent(): void
    {
        $this->seed(DemoEnvironmentSeeder::class);
        $before = $this->scenarioCounts();
        $this->seed(DemoEnvironmentSeeder::class);
        $this->assertSame($before, $this->scenarioCounts());
        $this->assertSame(1, League::query()->where('slug', DemoLeagueSeeder::LEAGUE_SLUG)->count());
        $this->assertSame(1, League::query()->where('slug', DemoHeadToHeadLeagueSeeder::LEAGUE_SLUG)->count());
        $this->assertSame(1, League::query()->where('slug', DemoHeadToHeadResultsSeeder::LEAGUE_SLUG)->count());
        $this->assertSame(count(DemoExtendedPlayerPoolSeeder::FREE_AGENTS), Player::query()
            ->whereIn('slug', collect(DemoExtendedPlayerPoolSeeder::FREE_AGENTS)->pluck(1))->count());
    }

    /** @return array<string, int> */
    private function scenarioCounts(): array
    {
        return [
            'players' => Player::query()->count(),
            'assignments' => FantasyTeamPlayer::query()->count(),
            'matches' => FantasyMatch::query()->count(),
            'formations' => Formation::query()->count(),
            'formation_players' => FormationPlayer::query()->count(),
            'player_scores' => PlayerScore::query()->count(),
            'team_scores' => TeamMatchdayScore::query()->count(),
            'score_details' => TeamMatchdayScoreDetail::query()->count(),
            'match_results' => FantasyMatchResult::query()->count(),
            'standings' => Standing::query()->count(),
        ];
    }
}
