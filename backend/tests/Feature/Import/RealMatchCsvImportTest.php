<?php

namespace Tests\Feature\Import;

use App\Enums\CsvImportType;
use App\Models\Matchday;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\RealMatch;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RealMatchCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private RealCompetition $competition;
    private Season $season;
    private Matchday $matchday;
    private SeasonClub $home;
    private SeasonClub $away;

    protected function setUp(): void
    {
        parent::setUp();
        $this->competition = RealCompetition::factory()->create(['code' => 'serie_a']);
        $this->season = Season::factory()->create(['real_competition_id' => $this->competition->id, 'name' => '2026/27']);
        $this->matchday = Matchday::factory()->create(['season_id' => $this->season->id, 'number' => 1]);
        $this->home = $this->club('Club-Home');
        $this->away = $this->club('Club-Away');
    }

    public function test_analysis_is_side_effect_free_and_execution_creates_offset_normalized_match(): void
    {
        $analysis = $this->analyse($this->row());
        $this->assertSame('create', $analysis['rows'][0]['action']);
        $this->assertDatabaseCount('real_matches', 0);

        $this->execute($analysis);

        $match = RealMatch::firstOrFail();
        $this->assertSame('2026-08-22 18:45:00', $match->kickoff_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(2, $match->home_score);
        $this->assertSame(1, $match->away_score);
    }

    public function test_identical_match_is_unchanged_and_supplied_mutable_fields_update(): void
    {
        $analysis = $this->analyse($this->row());
        $this->execute($analysis);
        $this->assertSame('unchanged', $this->analyse($this->row())['rows'][0]['action']);

        $updated = $this->analyse($this->row(kickoff: '2026-08-22T21:45:00+02:00', home: '0', away: '0', status: 'finished'));
        $this->assertSame('update', $updated['rows'][0]['action']);
        $this->execute($updated);
        $match = RealMatch::firstOrFail();
        $this->assertSame(0, $match->home_score);
        $this->assertSame(0, $match->away_score);
        $this->assertSame('finished', $match->status->value);
    }

    public function test_empty_optional_update_cells_preserve_values(): void
    {
        $this->execute($this->analyse($this->row()));
        $analysis = $this->analyse($this->row(kickoff: '', home: '', away: '', status: ''));
        $this->assertSame('unchanged', $analysis['rows'][0]['action']);
        $this->execute($analysis);
        $this->assertSame([2, 1, 'scheduled'], RealMatch::query()->get(['home_score', 'away_score', 'status'])->map(fn($match) => [$match->home_score, $match->away_score, $match->status->value])->first());
    }

    public function test_score_status_and_datetime_validation(): void
    {
        foreach (
            [
                $this->row(home: '-1'),
                $this->row(away: '65536'),
                $this->row(status: 'invalid'),
                $this->row(kickoff: '2026-08-22 20:45:00'),
            ] as $row
        ) {
            $this->assertSame('error', $this->analyse($row)['rows'][0]['action']);
        }
    }

    public function test_unknown_competition_season_and_matchday_are_rejected(): void
    {
        $rows = [
            str_replace('serie_a', 'unknown', $this->row()),
            str_replace('2026/27', '2027/28', $this->row()),
            str_replace(
                'serie_a,2026/27,1,',
                'serie_a,2026/27,2,',
                $this->row(),
            ),
        ];

        foreach ($rows as $row) {
            $this->assertSame('error', $this->analyse($row)['rows'][0]['action']);
        }
    }

    public function test_unknown_clubs_missing_season_membership_and_same_club_are_rejected(): void
    {
        $other = RealClub::factory()->create();
        RealClubExternalIdentity::create(['real_club_id' => $other->id, 'provider' => 'opta', 'external_id' => 'Global-Only']);
        foreach (
            [
                str_replace('Club-Home', 'Missing', $this->row()),
                str_replace('Club-Away', 'Missing', $this->row()),
                str_replace('Club-Home', 'Global-Only', $this->row()),
                str_replace('Club-Away', 'Club-Home', $this->row()),
            ] as $row
        ) $this->assertSame('error', $this->analyse($row)['rows'][0]['action']);
    }

    public function test_duplicate_resolved_identity_marks_every_row_error(): void
    {
        $analysis = $this->analyse($this->row() . "\n" . $this->row());
        $this->assertSame(['error', 'error'], array_column($analysis['rows'], 'action'));
    }

    public function test_existing_database_identity_is_not_a_duplicate_create(): void
    {
        $this->execute($this->analyse($this->row()));
        $analysis = $this->analyse($this->row(home: '3'));
        $this->assertSame('update', $analysis['rows'][0]['action']);
    }

    public function test_stale_dependency_identity_is_rejected_during_execution(): void
    {
        $analysis = $this->analyse($this->row());
        $anotherSeason = Season::factory()->create(['real_competition_id' => $this->competition->id]);
        $this->matchday->update(['season_id' => $anotherSeason->id]);
        $this->expectException(RuntimeException::class);
        $this->execute($analysis);
    }

    public function test_error_and_unchanged_rows_are_safely_skipped(): void
    {
        $error = $this->analyse($this->row(home: '-1'));
        $this->execute($error);
        $this->assertDatabaseCount('real_matches', 0);
        $this->execute($this->analyse($this->row()));
        $unchanged = $this->analyse($this->row());
        $this->execute($unchanged);
        $this->assertDatabaseCount('real_matches', 1);
    }

    private function club(string $externalId): SeasonClub
    {
        $club = RealClub::factory()->create();
        RealClubExternalIdentity::create(['real_club_id' => $club->id, 'provider' => 'opta', 'external_id' => $externalId]);
        return SeasonClub::factory()->create(['season_id' => $this->season->id, 'real_club_id' => $club->id]);
    }

    private function row(string $kickoff = '2026-08-22T20:45:00+02:00', string $home = '2', string $away = '1', string $status = 'scheduled'): string
    {
        return "serie_a,2026/27,1,opta,Club-Home,opta,Club-Away,{$kickoff},{$home},{$away},{$status}";
    }

    private function analyse(string $rows): array
    {
        $header = 'competition_code,season_name,matchday_number,home_club_provider,home_club_external_id,away_club_provider,away_club_external_id,kickoff_at,home_score,away_score,status';
        return app(CsvImportService::class)->analyse(CsvImportType::RealMatches, $header . "\n" . $rows . "\n");
    }

    private function execute(array $analysis): void
    {
        app(CsvImportService::class)->importer(CsvImportType::RealMatches)->execute($analysis);
    }
}
