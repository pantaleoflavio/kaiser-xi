<?php

namespace Tests\Feature\Import;

use App\Enums\CsvImportType;
use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use App\Models\RealClub;
use App\Models\RealCompetition;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseACsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_competition_analysis_is_side_effect_free_and_classifies_create_update_unchanged(): void
    {
        RealCompetition::create(['code' => 'one', 'name' => 'One', 'type' => 'custom', 'country_code' => 'IT', 'is_active' => true]);
        RealCompetition::create(['code' => 'two', 'name' => 'Old', 'type' => 'custom', 'is_active' => true]);
        $analysis = app(CsvImportService::class)->analyse(CsvImportType::RealCompetitions, "code,name,type,country_code,is_active\none,One,custom,IT,true\ntwo,New,custom,,true\nthree,Three,domestic_league,de,true\n");
        $this->assertSame(2, RealCompetition::count());
        $this->assertSame(['unchanged', 'update', 'create'], array_column($analysis['rows'], 'action'));
        $this->assertSame('DE', $analysis['rows'][2]['payload']['country_code']);
    }

    public function test_club_and_player_analysis_do_not_create_entities_or_mappings(): void
    {
        $service = app(CsvImportService::class);
        $club = $service->analyse(CsvImportType::RealClubs, "club_provider,club_external_id,club_slug,name,short_name\n Opta ,Club-001,fc-one,FC One,FCO\n");
        $player = $service->analyse(CsvImportType::Players, "player_provider,player_external_id,player_slug,display_name,birth_date\n Opta ,Player-001,jane-doe,Jane Doe,2000-01-02\n");
        $this->assertFalse($club['has_errors']);
        $this->assertFalse($player['has_errors']);
        $this->assertSame(0, RealClub::count());
        $this->assertSame(0, Player::count());
        $this->assertSame(0, PlayerExternalIdentity::count());
    }

    public function test_all_duplicate_competition_rows_are_errors(): void
    {
        $a = app(CsvImportService::class)->analyse(CsvImportType::RealCompetitions, "code,name,type\nfoo,Foo,custom\nFOO,Foo 2,custom\n");
        $this->assertSame(2, $a['counts']['errors']);
    }

    public function test_empty_optional_competition_values_are_not_supplied_on_update(): void
    {
        RealCompetition::create([
            'code' => 'serie_a',
            'name' => 'Serie A',
            'type' => 'domestic_league',
            'country_code' => 'IT',
            'is_active' => false,
        ]);

        $analysis = app(CsvImportService::class)->analyse(
            CsvImportType::RealCompetitions,
            "code,name,type,country_code,is_active\nserie_a,Serie A,domestic_league,,\n",
        );

        $this->assertSame('unchanged', $analysis['rows'][0]['action']);
        $this->assertSame([], $analysis['rows'][0]['changes']);
        $this->assertArrayNotHasKey('country_code', $analysis['rows'][0]['payload']);
        $this->assertArrayNotHasKey('is_active', $analysis['rows'][0]['payload']);
    }

    public function test_importers_ignore_non_executable_analysis_rows(): void
    {
        $analysis = [
            'rows' => [
                ['action' => 'error'],
                ['action' => 'unchanged'],
            ],
        ];

        foreach (CsvImportType::cases() as $type) {
            app(CsvImportService::class)->importer($type)->execute($analysis);
        }

        $this->assertDatabaseCount('real_competitions', 0);
        $this->assertDatabaseCount('real_clubs', 0);
        $this->assertDatabaseCount('players', 0);
    }
}
