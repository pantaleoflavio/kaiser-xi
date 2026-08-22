<?php

namespace Tests\Feature\Import;

use App\Enums\CsvImportType;
use App\Models\Matchday;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseBCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_seasons_with_bulk_resolved_competition_identity(): void
    {
        $competition = RealCompetition::factory()->create(['code' => 'serie_a']);

        $service = app(CsvImportService::class);
        $csv = "competition_code,season_name,starts_at,ends_at,is_active\nSerie A,2026/27,2026-08-01,2027-05-31,\n";

        $analysis = $service->analyse(CsvImportType::Seasons, $csv);

        $this->assertSame(1, $analysis['counts']['create']);
        $this->assertSame(0, Season::count());

        $service->importer(CsvImportType::Seasons)->execute($analysis);

        $season = Season::where('real_competition_id', $competition->id)->firstOrFail();
        $this->assertTrue($season->is_active);
    }

    public function test_it_imports_a_season_club_through_a_global_club_identity(): void
    {
        $season = Season::factory()->create();
        $club = RealClub::factory()->create();
        RealClubExternalIdentity::factory()->create([
            'real_club_id' => $club->id,
            'provider' => 'opta',
            'external_id' => 'Club-X',
        ]);
        $csv = "competition_code,season_name,club_provider,club_external_id,season_club_provider,season_club_external_id\n{$season->realCompetition->code},{$season->name}, OPTA ,Club-X, Feed ,Entry-X\n";
        $service = app(CsvImportService::class);

        $analysis = $service->analyse(CsvImportType::SeasonClubs, $csv);

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(0, SeasonClub::count());

        $service->importer(CsvImportType::SeasonClubs)->execute($analysis);

        $seasonClub = SeasonClub::firstOrFail();
        $this->assertSame('feed', $seasonClub->external_provider);
        $this->assertSame('Entry-X', $seasonClub->external_id);
    }

    public function test_it_imports_offset_aware_matchdays_with_a_nullable_name(): void
    {
        $season = Season::factory()->create();
        $csv = "competition_code,season_name,matchday_number,name,starts_at,ends_at\n{$season->realCompetition->code},{$season->name},1,,2026-08-01T20:00:00+02:00,2026-08-02T20:00:00+02:00\n";
        $service = app(CsvImportService::class);

        $analysis = $service->analyse(CsvImportType::Matchdays, $csv);

        $this->assertFalse($analysis['has_errors']);
        $this->assertSame(0, Matchday::count());

        $service->importer(CsvImportType::Matchdays)->execute($analysis);

        $matchday = Matchday::firstOrFail();
        $this->assertNull($matchday->name);
        $this->assertSame('18:00', $matchday->starts_at->format('H:i'));
    }
}
