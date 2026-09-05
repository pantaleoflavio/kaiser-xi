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
use App\Services\Import\RecoverableRowException;
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

            $import->update(['total_rows' => $analysis['counts']['total']]);

            DB::transaction(function () use ($type, &$analysis): void {
                foreach ($analysis['rows'] as $index => $row) {
                    if (in_array($row['action'], ['error', 'unmatched', 'unchanged'], true)) {
                        continue;
                    }

                    try {
                        DB::transaction(fn() => $this->importer($type)->execute([
                            'rows' => [$row],
                            'counts' => $analysis['counts'],
                        ]));
                    } catch (RecoverableRowException $exception) {
                        $analysis['rows'][$index] = $this->executionError($row, $exception->getMessage());
                    }
                }
            });

            $this->storeRejectedRows($import, $analysis);
            $rejected = collect($analysis['rows'])->whereIn('action', ['error', 'unmatched'])->count();

            $import->update([
                'status' => ImportStatus::Completed,
                'successful_rows' => $analysis['counts']['total'] - $rejected,
                'failed_rows' => $rejected,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->fail($import, $analysis, $exception->getMessage());

            throw $exception;
        }
    }

    public function storeUnmatchedRows(Import $import, array $analysis): void
    {
        $import->unmatchedRows()->delete();

        foreach ($analysis['rows'] as $row) {
            if ($row['action'] !== 'unmatched') continue;

            $import->unmatchedRows()->create([
                'row_number' => $row['row_number'],
                'row_data' => $row['data'],
                'message' => implode('; ', $row['warnings']),
            ]);
        }
    }

    public function storeRejectedRows(Import $import, array $analysis): void
    {
        $import->rowErrors()->delete();

        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['error', 'unmatched'], true)) {
                continue;
            }

            $errors = array_values(array_filter(array_merge($row['errors'] ?? [], $row['warnings'] ?? [])));
            $import->rowErrors()->create([
                'row_number' => $row['row_number'],
                'row_data' => $row['data'],
                'error_message' => implode('; ', $errors),
                'errors' => $errors,
            ]);
        }
    }

    public function rejectedRowsCsv(Import $import): string
    {
        $rows = $import->rowErrors()->orderBy('row_number')->get();
        $columns = $rows->flatMap(fn($error) => array_keys($error->row_data ?? []))->unique()->values()->all();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_merge(['row_number'], $columns, ['errors']), escape: '');

        foreach ($rows as $error) {
            $data = $error->row_data ?? [];
            fputcsv($stream, array_merge(
                [$error->row_number],
                array_map(fn(string $column): mixed => $data[$column] ?? '', $columns),
                [implode('; ', $error->errors ?: [$error->error_message])],
            ), escape: '');
        }

        rewind($stream);

        return stream_get_contents($stream);
    }

    private function executionError(array $row, string $message): array
    {
        return array_merge($row, [
            'action' => 'error',
            'changes' => [],
            'warnings' => [],
            'errors' => [$message],
        ]);
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
        $import->rowErrors()->delete();
        $import->rowErrors()->create([
            'row_number' => 1,
            'row_data' => null,
            'error_message' => $message,
            'errors' => [$message],
        ]);
    }
}
