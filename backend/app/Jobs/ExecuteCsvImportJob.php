<?php

namespace App\Jobs;

use App\Services\Import\CsvImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecuteCsvImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries;
    public int $timeout;

    public function __construct(public readonly int $importId)
    {
        $this->tries = (int) config('queue.imports.tries');
        $this->timeout = (int) config('queue.imports.timeout');
    }

    public function backoff(): int
    {
        return config('queue.imports.backoff');
    }

    public function handle(CsvImportService $imports): void
    {
        $imports->executeQueued($this->importId);
    }

    public function failed(?Throwable $exception): void
    {
        app(CsvImportService::class)->failQueuedExecution($this->importId, $exception?->getMessage() ?? 'The import job failed terminally.');
    }
}
