<?php

namespace Tests\Feature\Api\V1;

use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationModule;
use App\Models\League;
use App\Models\LeagueRole;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\League\LeagueSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow('2026-08-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_save_update_submit_and_read_after_deadline(): void
    {
        [$league, $team, $matchday, $payload] = $this->context();

        Sanctum::actingAs($team->user);

        $url = $this->url($league, $matchday, $team);

        // Prima PUT: crea la formation.
        $this->putJson($url, $payload)
            ->assertCreated()
            ->assertJsonPath('data.submitted', false);

        $payload['bench'] = [
            [
                'fantasy_team_player_id' => $this
                    ->assignment($league, $team, 'goalkeeper')
                    ->id,
                'order' => 1,
            ],
        ];

        // Seconda PUT: la formation esiste già, quindi è un update.
        $this->putJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.bench.0.order', 1);

        $this->postJson("{$url}/submit")
            ->assertOk()
            ->assertJsonPath('data.submitted', true);

        $this->assertSame(
            1,
            Formation::query()
                ->where('fantasy_team_id', $team->id)
                ->where('matchday_id', $matchday->id)
                ->count()
        );

        Carbon::setTestNow($matchday->starts_at);

        $this->putJson($url, $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'lineup_deadline_passed');

        $this->postJson("{$url}/submit")
            ->assertConflict()
            ->assertJsonPath('code', 'lineup_deadline_passed');

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.submitted', true);
    }

    public function test_other_users_and_invalid_nested_resources_cannot_edit(): void
    {
        [$league, $team, $matchday, $payload] = $this->context();
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->putJson($this->url($league, $matchday, $team), $payload)->assertForbidden();

        Sanctum::actingAs($team->user);
        $otherMatchday = Matchday::factory()->create(['season_id' => League::factory()->create()->season_id, 'starts_at' => now()->addDay()]);
        $this->putJson($this->url($league, $otherMatchday, $team), $payload)->assertNotFound();
    }

    public function test_historical_formation_visibility_uses_deadline_not_submission_time(): void
    {
        [$league, $team, $matchday, $payload] = $this->context();
        $member = User::factory()->create();
        $league->users()->attach($member, [
            'league_role_id' => LeagueRole::query()->where('key', 'participant')->firstOrFail()->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($team->user);
        $this->putJson($this->url($league, $matchday, $team), $payload)->assertCreated();
        $this->postJson($this->url($league, $matchday, $team) . '/submit')->assertOk();

        // Submission is not a disclosure event: the owner can read it, another member cannot.
        $this->getJson($this->url($league, $matchday, $team))->assertOk();
        Sanctum::actingAs($member);
        $this->getJson($this->url($league, $matchday, $team))->assertForbidden();

        Carbon::setTestNow($matchday->starts_at);
        $this->getJson($this->url($league, $matchday, $team))
            ->assertOk()
            ->assertJsonPath('data.fantasy_team.id', $team->id)
            ->assertJsonPath('data.submitted', true);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->url($league, $matchday, $team))->assertForbidden();

        $otherLeague = League::factory()->create(['season_id' => $league->season_id]);
        Sanctum::actingAs($member);
        $this->getJson($this->url($otherLeague, $matchday, $team))->assertNotFound();

        $otherMatchday = Matchday::factory()->create(['starts_at' => now()->subHour()]);
        $this->getJson($this->url($league, $otherMatchday, $team))->assertNotFound();
    }

    public function test_database_requirements_roster_bench_rules_are_enforced_transactionally(): void
    {
        [$league, $team, $matchday, $payload] = $this->context();

        Sanctum::actingAs($team->user);

        $url = $this->url($league, $matchday, $team);

        $this->putJson($url, $payload)
            ->assertCreated();
        $originalIds = Formation::query()->firstOrFail()->players()->pluck('fantasy_team_player_id')->all();

        $invalid = $payload;
        array_pop($invalid['starters']);
        $this->putJson($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors('starters');
        $this->assertEqualsCanonicalizing($originalIds, Formation::query()->firstOrFail()->players()->pluck('fantasy_team_player_id')->all());

        $invalid = $payload;
        $invalid['bench'] = [['fantasy_team_player_id' => $payload['starters'][0], 'order' => 1]];
        $this->putJson($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors('players');

        $released = FantasyTeamPlayer::query()->findOrFail($payload['starters'][0]);
        $released->update(['released_at' => now()]);
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('players');
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $league = League::factory()->create();
        app(LeagueSettingsService::class)->initializeDefaults($league);
        $league->users()->attach($owner, ['league_role_id' => LeagueRole::query()->where('key', 'participant')->firstOrFail()->id, 'joined_at' => now()]);
        $team = FantasyTeam::factory()->forLeagueAndUser($league, $owner)->create();
        $matchday = Matchday::factory()->create(['season_id' => $league->season_id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addDays(2)]);
        $module = FormationModule::query()->where('name', '4-3-3')->firstOrFail();
        $starters = [];
        foreach ($module->requirements()->with('playerRole')->get() as $requirement) {
            for ($i = 0; $i < $requirement->required_count; $i++) {
                $starters[] = $this->assignment($league, $team, $requirement->playerRole->key)->id;
            }
        }

        return [$league, $team, $matchday, ['formation_module_id' => $module->id, 'starters' => $starters, 'bench' => []]];
    }

    private function assignment(League $league, FantasyTeam $team, string $roleKey): FantasyTeamPlayer
    {
        $player = Player::factory()->create();
        PlayerSeasonRegistration::factory()->create(['player_id' => $player->id, 'season_club_id' => SeasonClub::factory()->create(['season_id' => $league->season_id])->id, 'player_role_id' => PlayerRole::query()->where('key', $roleKey)->firstOrFail()->id]);

        return FantasyTeamPlayer::factory()->create(['league_id' => $league->id, 'fantasy_team_id' => $team->id, 'player_id' => $player->id, 'assigned_at' => now()->subHour()]);
    }

    private function url(League $league, Matchday $matchday, FantasyTeam $team): string
    {
        return "/api/v1/leagues/{$league->id}/matchdays/{$matchday->id}/fantasy-teams/{$team->id}/formation";
    }
}
