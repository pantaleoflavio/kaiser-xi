<?php

namespace App\Services\Import\Importers;

use App\Services\Import\RecoverableRowException;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Services\Import\ImportRowAnalysis;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeasonCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        return [
            'columns' => ['competition_code', 'season_name', 'starts_at', 'ends_at', 'is_active'],
            'required_header' => ['competition_code', 'season_name'],
            'identifier' => 'competition_code + season_name',
            'required_create' => ['competition_code', 'season_name', 'starts_at', 'ends_at'],
            'optional' => ['is_active'],
            'formats' => ['starts_at / ends_at: YYYY-MM-DD', 'is_active: true or false'],
            'behavior' => 'Updates only supplied payload columns. New seasons are active by default.',
            'dependency' => 'RealCompetition must already exist. Import after real competitions.',
            'example' => ['bundesliga', '2026/27', '2026-08-01', '2027-05-31', 'true'],
            'caveats' => ['Competition codes are normalized to lowercase snake_case.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = [];
        $codes = [];
        $identities = [];
        foreach ($csv['rows'] as $row) {
            $data = $row['data'];
            $data['competition_code'] = RealCompetition::normalizeCode(
                $data['competition_code'],
            );
            $data['season_name'] = trim($data['season_name']);
            foreach (['starts_at', 'ends_at', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) $data[$field] = trim($data[$field]);
            }
            $identity = $data['competition_code'] . "\0" . $data['season_name'];
            $codes[] = $data['competition_code'];
            $identities[$identity][] = $row['row_number'];
            $prepared[] = array_merge($row, compact('data', 'identity'));
        }
        $competitions = RealCompetition::whereIn('code', array_unique($codes))->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', array_unique(array_column(array_column($prepared, 'data'), 'season_name')))->get()->keyBy(fn($s) => $s->real_competition_id . "\0" . $s->name);
        $results = [];
        foreach ($prepared as $row) {
            $d = $row['data'];
            $n = $row['row_number'];
            $label = $d['competition_code'] . ' / ' . $d['season_name'];
            if (count($identities[$row['identity']]) > 1) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Duplicate season identity also appears on CSV row ' . implode(', ', array_diff($identities[$row['identity']], [$n])) . '.');
                continue;
            }
            $competition = $competitions->get($d['competition_code']);
            if (! $competition) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Unknown competition_code.');
                continue;
            }
            $model = $seasons->get($competition->id . "\0" . $d['season_name']);
            $rules = ['competition_code' => ['required', 'string'], 'season_name' => ['required', 'string', 'max:255']];

            foreach (['starts_at', 'ends_at'] as $field) if (! $model || ($d[$field] ?? '') !== '') $rules[$field] = ['required', 'date_format:Y-m-d'];

            if (($d['ends_at'] ?? '') !== '' && ($d['starts_at'] ?? ($model?->starts_at?->format('Y-m-d'))) !== null) $rules['ends_at'][] = 'after_or_equal:starts_at';

            if (array_key_exists('is_active', $d) && $d['is_active'] !== '') {
                $rules['is_active'] = [Rule::in(['true', 'false'])];
            }

            try {
                $this->rows->validate($d + ['starts_at' => $model?->starts_at?->format('Y-m-d')], $rules);
            } catch (ValidationException $e) {
                $results[] = $this->rows->error($n, $row['data'], $label, $e->validator->errors()->all());
                continue;
            }

            $payload = array_intersect_key(
                $d,
                array_flip(['starts_at', 'ends_at', 'is_active'])
            );

            foreach (['starts_at', 'ends_at'] as $field) if (($payload[$field] ?? null) === '') unset($payload[$field]);

            if (array_key_exists('is_active', $payload)) {
                if ($payload['is_active'] === '') {
                    unset($payload['is_active']);
                } else {
                    $payload['is_active'] = $payload['is_active'] === 'true';
                }
            }

            if (! $model) {
                $payload += ['is_active' => true];
            }

            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $results[] = ['row_number' => $n, 'data' => $row['data'], 'identifier' => $label, 'action' => !$model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'competition_id' => $competition->id, 'name' => $d['season_name']];
        }
        return $this->rows->summarize($results);
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $model = Season::where('real_competition_id', $row['competition_id'])->where('name', $row['name'])->first();
            if (($row['model_id'] ?? null) !== $model?->id) throw new RecoverableRowException("Season identity changed since analysis at CSV row {$row['row_number']}.");
            $model ? $model->fill($row['payload'])->save() : Season::create(['real_competition_id' => $row['competition_id'], 'name' => $row['name']] + $row['payload']);
        }
    }
}
