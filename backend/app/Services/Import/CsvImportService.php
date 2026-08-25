<?php

namespace App\Services\Import;

use App\Enums\CsvImportType;
use App\Enums\ImportStatus;
use App\Jobs\ExecuteCsvImportJob;
use App\Models\Import;
use App\Services\Import\CsvParser;
use App\Services\Import\Importers\CsvImporter;
use App\Services\Import\Importers\MatchdayCsvImporter;
use App\Services\Import\Importers\PlayerCsvImporter;
use App\Services\Import\Importers\PlayerScoreCsvImporter;
use App\Services\Import\Importers\PlayerSeasonRegistrationCsvImporter;
use App\Services\Import\Importers\RealClubCsvImporter;
use App\Services\Import\Importers\RealCompetitionCsvImporter;
use App\Services\Import\Importers\RealMatchCsvImporter;
use App\Services\Import\Importers\SeasonClubCsvImporter;
use App\Services\Import\Importers\SeasonCsvImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CsvImportService
{
    public function __construct(private CsvParser $parser) {}

    public function importer(CsvImportType $type): CsvImporter
    {
        return app(match ($type) {
            CsvImportType::RealCompetitions => RealCompetitionCsvImporter::class,
            CsvImportType::RealClubs => RealClubCsvImporter::class,
            CsvImportType::Players => PlayerCsvImporter::class,
            CsvImportType::Seasons => SeasonCsvImporter::class,
            CsvImportType::SeasonClubs => SeasonClubCsvImporter::class,
            CsvImportType::Matchdays => MatchdayCsvImporter::class,
            CsvImportType::PlayerSeasonRegistrations => PlayerSeasonRegistrationCsvImporter::class,
            CsvImportType::RealMatches => RealMatchCsvImporter::class,
            CsvImportType::PlayerScores => PlayerScoreCsvImporter::class,
        });
    }

    public function contract(CsvImportType $type): array
    {
        return $this->importer($type)->contract();
    }

    public function template(CsvImportType $type, bool $example = false): string
    {
        $contract = $this->contract($type);
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $contract['columns'], escape: '');
        if ($example) fputcsv($stream, $contract['example'], escape: '');
        rewind($stream);
        return stream_get_contents($stream);
    }

    public function analyse(CsvImportType $type, string $contents): array
    {
        $contract = $this->contract($type);
        $csv = $this->parser->parse($contents, $contract['columns'], $contract['required_header']);
        return $this->importer($type)->analyse($csv) + ['checksum' => hash('sha256', $contents), 'header' => $csv['header']];
    }

    public function createHistory(CsvImportType $type, string $filename, string $contents, int $userId): Import
    {
        $path = 'csv-imports/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.csv';
        Storage::disk('local')->put($path, $contents);
        return Import::create(['type' => $type->value, 'filename' => basename($filename), 'disk' => 'local', 'path' => $path, 'checksum' => hash('sha256', $contents), 'status' => ImportStatus::Ready, 'imported_by_user_id' => $userId]);
    }

    public function queue(Import $import): bool
    {
        return DB::transaction(function () use ($import): bool {
            $locked = Import::query()->lockForUpdate()->findOrFail($import->getKey());

            if ($locked->status !== ImportStatus::Ready) {
                return false;
            }

            $locked->update(['status' => ImportStatus::Queued]);
            DB::afterCommit(function () use ($locked): void {
                ExecuteCsvImportJob::dispatch($locked->getKey());
            });

            return true;
        });
    }

    public function executeQueued(int $importId): void
    {
        $import = DB::transaction(function () use ($importId): ?Import {
            $locked = Import::query()->lockForUpdate()->findOrFail($importId);

            $staleBefore = now()->subSeconds(config('queue.imports.stale_after'));
            $recoverable = $locked->status === ImportStatus::Importing
                && $locked->started_at !== null
                && $locked->started_at->lte($staleBefore);

            if ($locked->status !== ImportStatus::Queued && ! $recoverable) {
                return null;
            }

            $locked->update([
                'status' => ImportStatus::Importing,
                'started_at' => now(),
            ]);

            return $locked;
        });

        if (! $import) {
            return;
        }

        $analysis = ['rows' => [], 'counts' => ['total' => 0]];

        try {
            $contents = Storage::disk($import->disk)->get($import->path);

            if (! hash_equals($import->checksum, hash('sha256', $contents))) {
                throw new \RuntimeException('The source file changed after analysis.');
            }

            $type = $import->type instanceof CsvImportType
                ? $import->type
                : CsvImportType::from($import->type);
            $analysis = $this->analyse($type, $contents);

            if ($analysis['has_errors']) {
                throw new \RuntimeException('Import is blocked by validation errors.');
            }

            $import->update(['total_rows' => $analysis['counts']['total']]);

            DB::transaction(fn() => $this->importer($type)->execute($analysis));

            $import->update([
                'status' => ImportStatus::Completed,
                'successful_rows' => $analysis['counts']['total'],
                'failed_rows' => 0,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->fail($import, $analysis, $exception->getMessage());

            throw $exception;
        }
    }

    public function failQueuedExecution(int $importId, string $message): void
    {
        DB::transaction(function () use ($importId, $message): void {
            $import = Import::query()->lockForUpdate()->find($importId);
            if (! $import || ! in_array($import->status, [ImportStatus::Queued, ImportStatus::Importing], true)) return;

            $this->fail($import, ['rows' => [], 'counts' => ['total' => $import->total_rows]], $message);
        });
    }

    private function fail(Import $import, array $analysis, string $message): void
    {
        $total = $analysis['counts']['total'] ?? 0;
        $import->update(['status' => ImportStatus::Failed, 'total_rows' => $total, 'successful_rows' => 0, 'failed_rows' => $total, 'completed_at' => now()]);

        if (($analysis['rows'] ?? []) === []) {
            $import->rowErrors()->create(['row_number' => 1, 'row_data' => null, 'error_message' => $message]);

            return;
        }

        $hasRowErrors = collect($analysis['rows'])->contains(fn(array $row): bool => $row['errors'] !== []);
        if (! $hasRowErrors) {
            $import->rowErrors()->create(['row_number' => 1, 'row_data' => null, 'error_message' => $message]);
            return;
        }

        foreach ($analysis['rows'] as $row) if ($row['errors']) $import->rowErrors()->create(['row_number' => $row['row_number'], 'row_data' => $row['data'], 'error_message' => implode('; ', $row['errors'])]);
    }
}
