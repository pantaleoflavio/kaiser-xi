<?php

namespace Tests\Feature\Api\V1;

use App\Exceptions\ChampionshipParticipantsMissingTeamsException;
use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\LeagueType;
use App\Models\Matchday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChampionshipInitializationReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_each_championship_type_reports_missing_teams_and_initializes_when_ready(): void
    {
        foreach (['classic', 'formula_one', 'head_to_head'] as $type) {
            [$league, $participant, $matchday] = $this->leagueMissingOneTeam($type);
            Sanctum::actingAs($league->commissioner);
            $path = match ($type) {
                'head_to_head' => 'head-to-head-schedule',
                'formula_one' => 'formula-one-championship',
                default => 'classic-championship',
            };
            $url = "/api/v1/leagues/{$league->id}/{$path}";

            $this->postJson($url, ['start_matchday_id' => $matchday->id])
                ->assertConflict()
                ->assertJsonPath('code', ChampionshipParticipantsMissingTeamsException::CODE)
                ->assertJsonPath('missing_team_count', 1);

            FantasyTeam::factory()->forLeagueAndUser($league, $participant)->create();

            $this->postJson($url, ['start_matchday_id' => $matchday->id])
                ->assertOk()
                ->assertJsonPath('data.initialized', true);
        }
    }

    private function leagueMissingOneTeam(string $type): array
    {
        $league = League::factory()->create([
            'league_type_id' => LeagueType::query()->where('key', $type)->value('id'),
        ]);
        $participant = User::factory()->create();
        $league->users()->attach([
            $league->commissioner_user_id => [
                'league_role_id' => LeagueRole::query()->where('key', 'commissioner')->value('id'),
                'joined_at' => now(),
            ],
            $participant->id => [
                'league_role_id' => LeagueRole::query()->where('key', 'participant')->value('id'),
                'joined_at' => now(),
            ],
        ]);
        FantasyTeam::factory()->forLeagueAndUser($league, $league->commissioner)->create();
        $matchday = Matchday::factory()->create([
            'season_id' => $league->season_id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        return [$league, $participant, $matchday];
    }
}
