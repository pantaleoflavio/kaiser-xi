<?php

namespace Tests\Feature\Filament;

use App\Enums\CsvImportType;
use App\Filament\Pages\ImportData;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\Import;
use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use App\Models\PlayerRole;
use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Role;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Models\User;
use App\Services\Import\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ImportDataUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stored_csv_is_analyzed_as_a_string_and_preserves_its_original_name(): void
    {
        Storage::fake('local');
        $this->seedReferenceData();
        $this->actingAs($this->administrator());

        $contents = "code,name,type,country_code,is_active\ntest,Test League,custom,DE,true\n";
        $upload = UploadedFile::fake()->createWithContent('original competition list.csv', $contents);

        $service = Mockery::spy(app(CsvImportService::class));
        app()->instance(CsvImportService::class, $service);

        Livewire::test(ImportData::class)
            ->set('data.type', CsvImportType::RealCompetitions->value)
            ->set('data.file', $upload)
            ->call('analyse')
            ->assertSet('analysis.has_errors', false)
            ->assertSet('data.file', null)
            ->assertSet('importId', fn(?int $id): bool => $id !== null);

        $service->shouldHaveReceived('analyse')->with(
            CsvImportType::RealCompetitions,
            Mockery::on(fn(mixed $value): bool => is_string($value) && $value === $contents),
        )->once();
        $this->assertDatabaseHas('imports', [
            'filename' => 'original competition list.csv',
            'type' => CsvImportType::RealCompetitions->value,
        ]);
        $this->assertSame([], Storage::disk('local')->files('csv-import-uploads'));
        $this->assertNotNull(Storage::disk('local')->get(Import::sole()->path));
    }

    public function test_a_stale_stored_upload_becomes_a_validation_error(): void
    {
        Storage::fake('local');
        $this->seedReferenceData();
        $this->actingAs($this->administrator());

        Livewire::test(ImportData::class)
            ->set('data.type', CsvImportType::RealCompetitions->value)
            // FileUpload keeps its hydrated component state as an array, even
            // when multiple uploads are disabled.
            ->set('data.file', ['csv-import-uploads/missing.csv'])
            ->set('data.original_name', 'missing.csv')
            ->call('analyse')
            ->assertHasErrors(['data.file'])
            ->assertSet('analysis', null)
            ->assertSet('importId', null)
            ->assertSet('data.file', null);

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_successful_analysis_can_still_be_confirmed_and_queued(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->seedReferenceData();
        $this->actingAs($this->administrator());

        $path = 'csv-import-uploads/stored.csv';
        Storage::disk('local')->put($path, "code,name,type,country_code,is_active\ntest,Test League,custom,DE,true\n");

        $component = Livewire::test(ImportData::class)
            ->set('data.type', CsvImportType::RealCompetitions->value)
            ->set('data.file', [$path])
            ->set('data.original_name', 'competition.csv')
            ->call('analyse');

        $importId = $component->get('importId');
        $component->call('confirm')->assertSet('importId', null);

        $this->assertDatabaseHas('imports', ['id' => $importId, 'status' => 'queued']);
        Queue::assertPushed(ExecuteCsvImportJob::class, fn(ExecuteCsvImportJob $job): bool => $job->importId === $importId);
    }

    public function test_analysis_with_rejected_rows_can_be_confirmed(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->seedReferenceData();
        $this->actingAs($this->administrator());

        $path = 'csv-import-uploads/rejected.csv';
        Storage::disk('local')->put($path, "code,name,type\ngood,Good,custom\nbad,Bad,invalid\n");

        $component = Livewire::test(ImportData::class)
            ->set('data.type', CsvImportType::RealCompetitions->value)
            ->set('data.file', [$path])
            ->set('data.original_name', 'rejected.csv')
            ->call('analyse')
            ->assertSet('analysis.counts.rejected', 1);

        $importId = $component->get('importId');
        $component->call('confirm')->assertSet('importId', null);

        $this->assertDatabaseHas('imports', ['id' => $importId, 'status' => 'queued', 'failed_rows' => 1]);
        $this->assertDatabaseHas('import_row_errors', ['import_id' => $importId, 'row_number' => 3]);
        Queue::assertPushed(ExecuteCsvImportJob::class);
    }

    public function test_player_registration_upload_with_pre_rename_date_headers_reaches_ready_analysis(): void
    {
        Storage::fake('local');
        $this->seedReferenceData();
        $this->actingAs($this->administrator());
        $competition = RealCompetition::factory()->create(['code' => 'serie_a']);
        $season = Season::factory()->create(['real_competition_id' => $competition->id, 'name' => '2026/27']);
        $player = Player::factory()->create();
        PlayerExternalIdentity::factory()->create(['player_id' => $player->id, 'provider' => 'opta', 'external_id' => 'Player-1']);
        $club = RealClub::factory()->create();
        RealClubExternalIdentity::factory()->create(['real_club_id' => $club->id, 'provider' => 'opta', 'external_id' => 'Club-1']);
        SeasonClub::factory()->create(['season_id' => $season->id, 'real_club_id' => $club->id]);
        PlayerRole::query()->firstOrCreate(['key' => 'forward'], ['label' => 'Forward', 'sort_order' => 1]);
        $contents = "competition_code,season_name,player_provider,player_external_id,club_provider,club_external_id,player_role,registered_at,released_at\nserie_a,2026/27,opta,Player-1,opta,Club-1,forward,2026-08-20,\n";

        $component = Livewire::test(ImportData::class)
            ->set('data.type', CsvImportType::PlayerSeasonRegistrations->value)
            ->set('data.file', UploadedFile::fake()->createWithContent('registrations.csv', $contents))
            ->call('analyse')
            ->assertSet('analysis.has_errors', false)
            ->assertSet('analysis.counts.create', 1)
            ->assertSet('importId', fn(?int $id): bool => $id !== null);

        $this->assertDatabaseHas('imports', [
            'id' => $component->get('importId'),
            'type' => CsvImportType::PlayerSeasonRegistrations->value,
            'status' => 'ready',
        ]);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'global_admin')->firstOrFail());

        return $user;
    }
}
