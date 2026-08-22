<?php

namespace App\Services\Import;

use Illuminate\Validation\ValidationException;

class CsvParser
{
    /** @return array{header: array<int,string>, rows: array<int,array{row_number:int,data:array<string,string>}>} */
    public function parse(string $contents, array $allowedColumns, array $requiredColumns): array
    {
        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'The CSV must be valid UTF-8.']);
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');

        if ($header === false || $header === [null]) {
            throw ValidationException::withMessages(['file' => 'The CSV header is missing.']);
        }

        $header = array_map(fn($value): string => trim((string) $value), $header);
        if (count($header) !== count(array_unique($header))) {
            throw ValidationException::withMessages(['file' => 'The CSV header contains duplicate columns.']);
        }
        foreach ($header as $column) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $column)) {
                throw ValidationException::withMessages(['file' => "Invalid CSV column: {$column}. Use lowercase snake_case."]);
            }
        }
        if ($unknown = array_diff($header, $allowedColumns)) {
            throw ValidationException::withMessages(['file' => 'Unknown CSV columns: ' . implode(', ', $unknown) . '.']);
        }
        if ($missing = array_diff($requiredColumns, $header)) {
            throw ValidationException::withMessages(['file' => 'Missing required CSV columns: ' . implode(', ', $missing) . '.']);
        }

        $rows = [];
        $physicalRow = 1;
        while (($values = fgetcsv($stream, escape: '')) !== false) {
            $physicalRow++;
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            if (count($values) !== count($header)) {
                throw ValidationException::withMessages(['file' => "CSV row {$physicalRow} has the wrong number of columns."]);
            }
            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = (string) $values[$index];
            }
            $rows[] = ['row_number' => $physicalRow, 'data' => $data];
        }
        fclose($stream);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The CSV must contain at least one data row.']);
        }

        return compact('header', 'rows');
    }
}
