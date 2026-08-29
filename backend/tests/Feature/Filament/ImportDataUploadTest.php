<?php

namespace Tests\Feature\Filament;

use App\Enums\CsvImportType;
use App\Filament\Pages\ImportData;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\Import;
use App\Models\Role;
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

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'global_admin')->firstOrFail());

        return $user;
    }
}
