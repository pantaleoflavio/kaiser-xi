<?php

namespace Tests\Feature\Import;

use App\Enums\CompetitionType;
use App\Enums\CsvImportType;
use App\Enums\ImportStatus;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\Import;
use App\Models\RealCompetition;
use App\Models\User;
use App\Services\Import\CsvImportService;
use App\Services\Import\Importers\RealCompetitionCsvImporter;
use App\Services\Import\RecoverableRowException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExecuteCsvImportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_ready_import_is_dispatched_once_without_synchronous_domain_writes(): void
    {
        Queue::fake();
        $import = $this->readyImport("code,name,type\nserie_a,Serie A,domestic_league\n");
        $service = app(CsvImportService::class);

        $this->assertTrue($service->queue($import));
        $this->assertFalse($service->queue($import->refresh()));

        Queue::assertPushed(ExecuteCsvImportJob::class, 1);
        $this->assertSame(0, RealCompetition::query()->count());
        $this->assertSame(ImportStatus::Queued, $import->refresh()->status);
    }

    public function test_blocked_import_cannot_be_dispatched(): void
    {
        Queue::fake();
        $import = $this->readyImport("code,name,type\nbad,Bad,custom\n");
        $import->update(['status' => ImportStatus::Blocked]);

        $this->assertFalse(app(CsvImportService::class)->queue($import));
        Queue::assertNothingPushed();
    }

    public function test_outer_transaction_rollback_does_not_dispatch_import_job(): void
    {
        Queue::fake();
        $import = $this->readyImport("code,name,type\nrollback,Rollback,custom\n");

        DB::beginTransaction();
        app(CsvImportService::class)->queue($import);
        Queue::assertNothingPushed();
        DB::rollBack();

        Queue::assertNothingPushed();
        $this->assertSame(ImportStatus::Ready, $import->refresh()->status);
    }

    public function test_outer_transaction_commit_dispatches_import_job_once(): void
    {
        Queue::fake();
        $import = $this->readyImport("code,name,type\ncommit,Commit,custom\n");

        DB::beginTransaction();
        app(CsvImportService::class)->queue($import);
        Queue::assertNothingPushed();
        DB::commit();

        Queue::assertPushed(ExecuteCsvImportJob::class, 1);
        $this->assertSame(ImportStatus::Queued, $import->refresh()->status);
    }

    public function test_job_executes_real_import_and_is_idempotent(): void
    {
        Queue::fake();
        $import = $this->readyImport("code,name,type\nserie_a,Serie A,domestic_league\n");
        app(CsvImportService::class)->queue($import);
        $job = new ExecuteCsvImportJob($import->id);

        $job->handle(app(CsvImportService::class));
        $job->handle(app(CsvImportService::class));

        $this->assertDatabaseCount('real_competitions', 1);
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
    }

    public function test_analysis_errors_are_skipped_while_valid_rows_complete(): void
    {
        $import = $this->readyImport("code,name,type\none,One,custom\nbad,Bad,not-a-type\nthree,Three,custom\n");
        app(CsvImportService::class)->queue($import);

        (new ExecuteCsvImportJob($import->id))->handle(app(CsvImportService::class));

        $import->refresh();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(2, $import->successful_rows);
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(['one', 'three'], RealCompetition::query()->orderBy('id')->pluck('code')->all());
        $error = $import->rowErrors()->sole();
        $this->assertSame(3, $error->row_number);
        $this->assertSame('bad', $error->row_data['code']);
        $this->assertNotEmpty($error->errors);
    }

    public function test_all_invalid_rows_complete_with_rejected_diagnostics(): void
    {
        $import = $this->readyImport("code,name,type\none,One,invalid\ntwo,Two,also-invalid\n");
        app(CsvImportService::class)->queue($import);

        (new ExecuteCsvImportJob($import->id))->handle(app(CsvImportService::class));

        $import->refresh();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(0, $import->successful_rows);
        $this->assertSame(2, $import->failed_rows);
        $this->assertDatabaseCount('real_competitions', 0);
        $this->assertCount(2, $import->rowErrors);
    }

    public function test_rejected_csv_is_generated_from_persisted_rows_and_cascades_on_delete(): void
    {
        $import = $this->readyImport("code,name,type\nbad,Bad,invalid\n");
        app(CsvImportService::class)->queue($import);
        (new ExecuteCsvImportJob($import->id))->handle(app(CsvImportService::class));

        $csv = app(CsvImportService::class)->rejectedRowsCsv($import->refresh());
        $this->assertStringContainsString('row_number,code,name,type,errors', $csv);
        $this->assertStringContainsString('2,bad,Bad,invalid', $csv);
        $this->assertStringContainsString('selected type is invalid', $csv);

        $id = $import->id;
        $import->delete();
        $this->assertDatabaseMissing('import_row_errors', ['import_id' => $id]);
    }

    public function test_execution_time_row_failure_rolls_back_only_that_row(): void
    {
        $import = $this->readyImport("code,name,type\none,One,custom\ntwo,Two,custom\nthree,Three,custom\n");
        app(CsvImportService::class)->queue($import);
        $realImporter = app(RealCompetitionCsvImporter::class);

        $this->app->instance(RealCompetitionCsvImporter::class, new class($realImporter) extends RealCompetitionCsvImporter
        {
            public function __construct(private readonly RealCompetitionCsvImporter $realImporter) {}
            public function contract(): array
            {
                return $this->realImporter->contract();
            }
            public function analyse(array $csv): array
            {
                return $this->realImporter->analyse($csv);
            }
            public function execute(array $analysis): void
            {
                if ($analysis['rows'][0]['identifier'] === 'two') {
                    RealCompetition::create(['code' => 'partial', 'name' => 'Partial', 'type' => CompetitionType::Custom, 'is_active' => true]);
                    throw new RecoverableRowException('The row became invalid during execution.');
                }
                $this->realImporter->execute($analysis);
            }
        });

        (new ExecuteCsvImportJob($import->id))->handle(app(CsvImportService::class));

        $this->assertSame(['one', 'three'], RealCompetition::query()->orderBy('id')->pluck('code')->all());
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(3, $import->rowErrors()->sole()->row_number);
    }

    public function test_execution_failure_rolls_back_domain_writes_and_retains_diagnostics(): void
    {
        Queue::fake();

        $import = $this->readyImport(
            "code,name,type\none,One,custom\ntwo,Two,custom\n"
        );

        app(CsvImportService::class)->queue($import);

        $realImporter = app(RealCompetitionCsvImporter::class);

        $this->app->instance(
            RealCompetitionCsvImporter::class,
            new class($realImporter) extends RealCompetitionCsvImporter
            {
                public function __construct(
                    private readonly RealCompetitionCsvImporter $realImporter,
                ) {}

                public function contract(): array
                {
                    return $this->realImporter->contract();
                }

                public function analyse(array $csv): array
                {
                    return $this->realImporter->analyse($csv);
                }

                public function execute(array $analysis): void
                {
                    RealCompetition::query()->create([
                        'code' => 'one',
                        'name' => 'One',
                        'type' => CompetitionType::Custom,
                        'is_active' => true,
                    ]);

                    throw new \RuntimeException('controlled import failure');
                }
            },
        );

        try {
            (new ExecuteCsvImportJob($import->id))
                ->handle(app(CsvImportService::class));

            $this->fail('The controlled import failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'controlled import failure',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('real_competitions', 0);

        $this->assertSame(
            ImportStatus::Failed,
            $import->refresh()->status,
        );

        $this->assertTrue(
            $import->rowErrors()->exists(),
        );
    }

    public function test_non_stale_importing_or_completed_and_failed_import_is_not_executed(): void
    {
        foreach ([ImportStatus::Importing, ImportStatus::Completed, ImportStatus::Failed] as $status) {
            $import = $this->readyImport("code,name,type\n{$status->value},Test,custom\n");
            $import->update(['status' => $status, 'started_at' => now()]);
            (new ExecuteCsvImportJob($import->id))->handle(app(CsvImportService::class));
        }

        $this->assertDatabaseCount('real_competitions', 0);
    }

    public function test_stale_importing_import_is_recovered_once(): void
    {
        config()->set('queue.imports.stale_after', 60);
        $import = $this->readyImport("code,name,type\nrecovered,Recovered,custom\n");
        $import->update(['status' => ImportStatus::Importing, 'started_at' => now()->subMinutes(2)]);

        $job = new ExecuteCsvImportJob($import->id);
        $job->handle(app(CsvImportService::class));
        $job->handle(app(CsvImportService::class));

        $this->assertDatabaseCount('real_competitions', 1);
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);
    }

    public function test_terminal_job_failure_marks_import_failed_with_one_diagnostic(): void
    {
        $import = $this->readyImport("code,name,type\none,One,custom\ntwo,Two,custom\n");
        $import->update(['status' => ImportStatus::Importing, 'started_at' => now()]);

        (new ExecuteCsvImportJob($import->id))->failed(new \RuntimeException('worker terminated'));

        $this->assertSame(ImportStatus::Failed, $import->refresh()->status);
        $this->assertSame(1, $import->rowErrors()->count());
        $this->assertSame('worker terminated', $import->rowErrors()->sole()->error_message);
    }

    private function readyImport(string $contents): Import
    {
        return app(CsvImportService::class)->createHistory(
            CsvImportType::RealCompetitions,
            'competitions.csv',
            $contents,
            User::factory()->create()->id,
        );
    }
}
