<?php

namespace Tests\Feature\Seeders;

use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\League;
use App\Models\Matchday;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Models\TeamMatchdayScoreDetail;
use Database\Seeders\DemoHeadToHeadResultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Seeders\Concerns\SeedsDemoFoundation;
use Tests\TestCase;

class DemoHeadToHeadResultsSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoFoundation;

    public function test_results_arena_is_complete_isolated_and_independently_idempotent(): void
    {
        $this->seedDemoFoundation();
        $this->seed(DemoHeadToHeadResultsSeeder::class);

        $league = League::query()->where('slug', DemoHeadToHeadResultsSeeder::LEAGUE_SLUG)->firstOrFail();
        $scheduledIds = FantasyMatch::query()->whereBelongsTo($league)->distinct()->pluck('matchday_id');
        $pastIds = Matchday::query()->whereIn('id', $scheduledIds)->where('number', '<', 94)->pluck('id');
        $current = Matchday::query()->where('season_id', $league->season_id)->where('number', 94)->firstOrFail();
        $futureIds = Matchday::query()->whereIn('id', $scheduledIds)->where('number', '>', 94)->pluck('id');

        $this->assertTrue($league->hasInitializedHeadToHeadSchedule());
        $this->assertSame(90, $league->h2hStartMatchday->number);
        $this->assertSame(6, $league->fantasyTeams()->count());
        $this->assertSame(14, $scheduledIds->count());
        $this->assertSame(42, FantasyMatch::query()->whereBelongsTo($league)->count());
        $this->assertSame(4, $pastIds->count());
        $this->assertSame(12, FantasyMatchResult::query()->whereHas('fantasyMatch', fn($query) => $query
            ->whereBelongsTo($league)->whereIn('matchday_id', $pastIds))->count());
        $this->assertSame(6, Standing::query()->whereBelongsTo($league)->count());
        $this->assertSame(3, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)
            ->whereNotNull('submitted_at')->count());
        $this->assertSame(1, Formation::query()->whereBelongsTo($league)->whereBelongsTo($current)
            ->whereNull('submitted_at')->count());
        $this->assertSame(0, TeamMatchdayScore::query()->whereBelongsTo($league)->whereBelongsTo($current)->count());
        $this->assertSame(0, Formation::query()->whereBelongsTo($league)->whereIn('matchday_id', $futureIds)->count());

        $counts = $this->counts($league);
        $this->seed(DemoHeadToHeadResultsSeeder::class);
        $this->assertSame($counts, $this->counts($league));
    }

    /** @return array<string, int> */
    private function counts(League $league): array
    {
        $formationIds = Formation::query()->whereBelongsTo($league)->pluck('id');
        $scoreIds = TeamMatchdayScore::query()->whereBelongsTo($league)->pluck('id');

        return [
            'assignments' => FantasyTeamPlayer::query()->whereBelongsTo($league)->count(),
            'matches' => FantasyMatch::query()->whereBelongsTo($league)->count(),
            'results' => FantasyMatchResult::query()->whereHas('fantasyMatch', fn($query) => $query->whereBelongsTo($league))->count(),
            'formations' => $formationIds->count(),
            'formation_players' => FormationPlayer::query()->whereIn('formation_id', $formationIds)->count(),
            'team_scores' => $scoreIds->count(),
            'score_details' => TeamMatchdayScoreDetail::query()->whereIn('team_matchday_score_id', $scoreIds)->count(),
            'standings' => Standing::query()->whereBelongsTo($league)->count(),
        ];
    }
}
