<?php

namespace App\Services\Import\Importers;

use App\Enums\CompetitionType;
use App\Models\RealCompetition;
use App\Services\Import\ImportRowAnalysis;
use App\Services\Import\RecoverableRowException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RealCompetitionCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        $types = array_column(CompetitionType::cases(), 'value');
        return [
            'columns' => ['code', 'name', 'type', 'country_code', 'is_active'],
            'required_header' => ['code'],
            'identifier' => 'code',
            'required_create' => ['code', 'name', 'type'],
            'optional' => ['country_code', 'is_active'],
            'formats' => ['type: ' . implode(', ', $types), 'country_code: two letters', 'is_active: true or false'],
            'behavior' => 'Updates only non-empty supplied columns; empty optional cells preserve existing values.',
            'dependency' => 'Import before seasons.',
            'example' => ['bundesliga', 'Bundesliga', CompetitionType::DomesticLeague->value, 'DE', 'true'],
            'caveats' => ['Codes are normalized to lowercase snake_case; names are never identities.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = [];
        $codes = [];
        foreach ($csv['rows'] as $row) {
            $data = $row['data'];
            $data['code'] = RealCompetition::normalizeCode($data['code']);
            foreach (['name', 'type', 'country_code', 'is_active'] as $field) if (array_key_exists($field, $data)) $data[$field] = trim($data[$field]);
            if (isset($data['country_code']) && $data['country_code'] !== '') $data['country_code'] = strtoupper($data['country_code']);
            $prepared[] = $row + ['normalized' => $data];
            $codes[$data['code']][] = $row['row_number'];
        }
        $existing = RealCompetition::whereIn('code', array_keys($codes))->get()->keyBy('code');
        $results = [];
        foreach ($prepared as $row) {
            $data = $row['normalized'];
            $number = $row['row_number'];
            if (count($codes[$data['code']]) > 1) {
                $others = implode(', ', array_diff($codes[$data['code']], [$number]));
                $results[] = $this->rows->error($number, $row['data'], $data['code'], "Duplicate competition code also appears on CSV row {$others}");
                continue;
            }
            $model = $existing->get($data['code']);
            $rules = ['code' => ['required', 'string', 'max:255']];
            foreach (['name', 'type'] as $field) if (! $model || ($data[$field] ?? '') !== '') $rules[$field] = ['required', 'string', $field === 'type' ? Rule::enum(CompetitionType::class) : 'max:255'];
            if (($data['country_code'] ?? '') !== '') $rules['country_code'] = ['alpha:ascii', 'size:2'];
            if (($data['is_active'] ?? '') !== '') $rules['is_active'] = [Rule::in(['true', 'false'])];
            try {
                $this->rows->validate($data, $rules);
            } catch (ValidationException $e) {
                $results[] = $this->rows->error($number, $row['data'], $data['code'], $e->validator->errors()->all());
                continue;
            }
            $payload = array_intersect_key($data, array_flip(['name', 'type', 'country_code', 'is_active']));
            $payload = array_filter($payload, static fn(string $value): bool => $value !== '');
            if (isset($payload['is_active'])) $payload['is_active'] = $payload['is_active'] === 'true';
            if (! $model) $payload += ['is_active' => true];
            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $results[] = ['row_number' => $number, 'data' => $row['data'], 'identifier' => $data['code'], 'action' => ! $model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'identity' => $data['code']];
        }
        return $this->rows->summarize($results);
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $model = RealCompetition::where('code', $row['identity'])->first();
            if (($row['model_id'] ?? null) !== $model?->id) throw new RecoverableRowException("Competition identity changed since analysis at CSV row {$row['row_number']}.");
            if ($model) $model->fill($row['payload'])->save();
            else RealCompetition::create(['code' => $row['identity']] + $row['payload']);
        }
    }
}
