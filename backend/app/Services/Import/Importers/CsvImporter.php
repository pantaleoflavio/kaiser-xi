<?php

namespace App\Services\Import\Importers;

interface CsvImporter
{
    public function contract(): array;

    /** @param array{header:array,rows:array} $csv */
    public function analyse(array $csv): array;

    public function execute(array $analysis): void;
}
