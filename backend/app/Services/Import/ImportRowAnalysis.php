<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Validator;

class ImportRowAnalysis
{
    public function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    public function summarize(array $rows): array
    {
        $counts = [
            'total' => count($rows),
            'create' => 0,
            'update' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'rejected' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['action'] === 'error' ? 'errors' : $row['action']]++;
            if (in_array($row['action'], ['error', 'unmatched'], true)) {
                $counts['rejected']++;
            }
            $counts['warnings'] += count($row['warnings'] ?? []);
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
            'has_errors' => $counts['errors'] > 0,
            'has_rejected_rows' => $counts['rejected'] > 0,
            'has_fatal_errors' => false,
        ];
    }

    public function error(int $number, array $data, string $identifier, array|string $messages): array
    {
        return [
            'row_number' => $number,
            'data' => $data,
            'identifier' => $identifier,
            'action' => 'error',
            'changes' => [],
            'warnings' => [],
            'errors' => (array) $messages,
        ];
    }

    public function changedFields(object $model, array $payload): array
    {
        $changes = [];

        foreach ($payload as $field => $value) {
            $current = $model->getAttribute($field);

            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }

            if ($current instanceof \DateTimeInterface) {
                $date = \DateTimeImmutable::createFromInterface($current);

                $current = strlen((string) $value) === 10
                    ? $date->format('Y-m-d')
                    : $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }

            if ((string) $current !== (string) $value) {
                $changes[] = $field;
            }
        }

        return $changes;
    }
}
