<?php

namespace App\Jobs;

use App\Services\Import\CsvImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteCsvImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $importId) {}

    public function handle(CsvImportService $imports): void
    {
        $imports->executeQueued($this->importId);
    }
}
