<?php

namespace App\Services\Import\Importers;

use App\Models\Matchday;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Services\Import\ImportRowAnalysis;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class MatchdayCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        return [
            'columns' => ['competition_code', 'season_name', 'matchday_number', 'name', 'starts_at', 'ends_at'],
            'required_header' => ['competition_code', 'season_name', 'matchday_number'],
            'identifier' => 'competition_code + season_name + matchday_number',
            'required_create' => ['competition_code', 'season_name', 'matchday_number', 'starts_at', 'ends_at'],
            'optional' => ['name'],
            'formats' => ['matchday_number: integer 0–65535', 'starts_at / ends_at: ISO-8601 datetime with explicit offset'],
            'behavior' => 'Updates only supplied payload columns; an empty name clears it.',
            'dependency' => 'Season must already exist. Import after seasons.',
            'example' => ['bundesliga', '2026/27', '1', '', '2026-08-21T20:30:00+02:00', '2026-08-23T20:30:00+02:00'],
            'caveats' => ['Timezone-less timestamps are rejected.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = [];
        $codes = [];
        $keys = [];
        foreach ($csv['rows'] as $row) {
            $d = $row['data'];
            $d['competition_code'] = RealCompetition::normalizeCode(
                $d['competition_code'],
            );
            $d['season_name'] = trim($d['season_name']);
            $d['matchday_number'] = trim($d['matchday_number']);
            foreach (['name', 'starts_at', 'ends_at'] as $f) if (array_key_exists($f, $d)) $d[$f] = trim($d[$f]);
            $key = $d['competition_code'] . "\0" . $d['season_name'] . "\0" . $d['matchday_number'];
            $keys[$key][] = $row['row_number'];
            $codes[] = $d['competition_code'];
            $prepared[] = $row + ['normalized' => $d, 'key' => $key];
        }
        $competitions = RealCompetition::whereIn('code', array_unique($codes))->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', array_unique(array_map(fn($r) => $r['normalized']['season_name'], $prepared)))->get()->keyBy(fn($s) => $s->real_competition_id . "\0" . $s->name);
        $matchdays = Matchday::whereIn('season_id', $seasons->pluck('id'))->whereIn('number', array_unique(array_map(fn($r) => $r['normalized']['matchday_number'], $prepared)))->get()->keyBy(fn($m) => $m->season_id . "\0" . $m->number);
        $results = [];
        foreach ($prepared as $row) {
            $d = $row['normalized'];
            $n = $row['row_number'];
            $label = $d['competition_code'] . ' / ' . $d['season_name'] . ' / ' . $d['matchday_number'];
            if (count($keys[$row['key']]) > 1) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Duplicate matchday identity also appears on CSV row ' . implode(', ', array_diff($keys[$row['key']], [$n])) . '.');
                continue;
            }
            $competition = $competitions->get($d['competition_code']);
            $season = $competition ? $seasons->get($competition->id . "\0" . $d['season_name']) : null;
            if (!$season) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Unknown season.');
                continue;
            }
            $model = $matchdays->get($season->id . "\0" . $d['matchday_number']);
            $rules = ['competition_code' => ['required'], 'season_name' => ['required'], 'matchday_number' => ['required', 'integer', 'between:0,65535']];
            if (array_key_exists('name', $d)) $rules['name'] = ['nullable', 'string', 'max:255'];
            foreach (['starts_at', 'ends_at'] as $f) if (!$model || array_key_exists($f, $d)) $rules[$f] = ['required', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/'];
            try {
                $this->rows->validate($d, $rules);
                foreach (['starts_at', 'ends_at'] as $f) if (isset($d[$f])) $d[$f] = CarbonImmutable::parse($d[$f])->utc()->format('Y-m-d H:i:s');
                $start = $d['starts_at'] ?? $model?->starts_at?->utc()->format('Y-m-d H:i:s');
                $end = $d['ends_at'] ?? $model?->ends_at?->utc()->format('Y-m-d H:i:s');
                if ($end < $start) throw ValidationException::withMessages(['ends_at' => 'The ends at field must be after or equal to starts at.']);
            } catch (ValidationException $e) {
                $results[] = $this->rows->error($n, $row['data'], $label, $e->validator->errors()->all());
                continue;
            } catch (\Throwable) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Invalid ISO-8601 timestamp.');
                continue;
            }
            $payload = array_intersect_key($d, array_flip(['name', 'starts_at', 'ends_at']));
            if (array_key_exists('name', $payload) && $payload['name'] === '') $payload['name'] = null;
            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $results[] = ['row_number' => $n, 'data' => $row['data'], 'identifier' => $model?->displayLabel() ?? $label, 'action' => !$model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'season_id' => $season->id, 'number' => (int)$d['matchday_number']];
        }
        return $this->rows->summarize($results);
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if ($row['action'] === 'unchanged') continue;
            $model = Matchday::where('season_id', $row['season_id'])->where('number', $row['number'])->first();
            if (($row['model_id'] ?? null) !== $model?->id) throw new \RuntimeException("Matchday identity changed since analysis at CSV row {$row['row_number']}.");
            $model ? $model->fill($row['payload'])->save() : Matchday::create(['season_id' => $row['season_id'], 'number' => $row['number']] + $row['payload']);
        }
    }
}
