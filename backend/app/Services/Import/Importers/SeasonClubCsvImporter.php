<?php

namespace App\Services\Import\Importers;

use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\ImportRowAnalysis;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeasonClubCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        return [
            'columns' => ['competition_code', 'season_name', 'club_provider', 'club_external_id', 'club_slug', 'season_club_provider', 'season_club_external_id', 'display_name', 'is_active'],
            'required_header' => ['competition_code', 'season_name'],
            'identifier' => 'competition_code + season_name + resolved RealClub',
            'required_create' => ['competition_code', 'season_name', 'club_provider + club_external_id or club_slug'],
            'optional' => ['season_club_provider + season_club_external_id', 'display_name', 'is_active'],
            'formats' => ['providers: trim + lowercase', 'external IDs: opaque', 'is_active: true or false'],
            'behavior' => 'club_provider/external_id identify the persistent RealClub; season_club_provider/external_id identify its participation in this Season.',
            'dependency' => 'Season and RealClub must already exist. Import after seasons and real clubs.',
            'example' => ['bundesliga', '2026/27', 'opta', 'Club-001', 'fc-one', 'opta', 'Entry-001', 'FC One', 'true'],
            'caveats' => ['Names are never used for matching. Both RealClub identities must agree when both are supplied.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = $this->prepare($csv['rows']);
        $codes = array_unique(array_column($prepared, 'competition_code'));
        $competitions = RealCompetition::whereIn('code', $codes)->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', array_unique(array_column($prepared, 'season_name')))->get()->keyBy(fn($s) => $s->real_competition_id . "\0" . $s->name);
        $clubs = RealClub::whereIn('slug', array_filter(array_unique(array_column($prepared, 'club_slug'))))->get()->keyBy('slug');
        $pairs = array_filter(array_unique(array_column($prepared, 'club_pair')));
        $identities = collect();
        if ($pairs !== []) {
            $identities = RealClubExternalIdentity::with('realClub')->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    [$provider, $external] = explode("\0", $pair, 2);
                    $query->orWhere(fn($q) => $q->where('provider', $provider)->where('external_id', $external));
                }
            })->get()->keyBy(fn($i) => $i->provider . "\0" . $i->external_id);
        }

        foreach ($prepared as &$row) {
            $competition = $competitions->get($row['competition_code']);
            $row['season'] = $competition ? $seasons->get($competition->id . "\0" . $row['season_name']) : null;
            $row['by_pair'] = $identities->get($row['club_pair'])?->realClub;
            $row['by_slug'] = $clubs->get($row['club_slug']);
            $row['club'] = $row['by_pair'] ?: $row['by_slug'];
        }
        unset($row);

        $seasonIds = collect($prepared)->pluck('season.id')->filter()->unique();
        $existing = SeasonClub::whereIn('season_id', $seasonIds)->get()->keyBy(fn($s) => $s->season_id . "\0" . $s->real_club_id);
        $external = SeasonClub::whereNotNull('external_provider')->get()->keyBy(fn($s) => $s->external_provider . "\0" . $s->external_id);
        $naturalDuplicates = $this->duplicateRows($prepared, fn($r) => $r['season'] && $r['club'] ? $r['season']->id . "\0" . $r['club']->id : null);
        $externalDuplicates = $this->duplicateRows($prepared, fn($r) => $r['season_pair'] ?: null);
        $results = [];

        foreach ($prepared as $row) {
            $n = $row['row_number'];
            $label = $row['competition_code'] . ' / ' . $row['season_name'] . ' / ' . ($row['club_provider'] ?: $row['club_slug']);
            $error = $this->referenceError($row);
            if (! $error && isset($naturalDuplicates[$n])) $error = 'Duplicate natural identity also appears on CSV row ' . implode(', ', $naturalDuplicates[$n]) . '.';
            if (! $error && isset($externalDuplicates[$n])) $error = 'Duplicate seasonal external identity also appears on CSV row ' . implode(', ', $externalDuplicates[$n]) . '.';
            if ($error) {
                $results[] = $this->rows->error($n, $row['original'], $label, $error);
                continue;
            }
            $key = $row['season']->id . "\0" . $row['club']->id;
            $model = $existing->get($key);
            $owner = $external->get($row['season_pair']);
            if ($owner && $owner->id !== $model?->id) {
                $results[] = $this->rows->error($n, $row['original'], $label, 'Seasonal external identity belongs to another SeasonClub.');
                continue;
            }
            $rules = [];
            if ($row['has_display_name']) $rules['display_name'] = ['nullable', 'string', 'max:255'];
            if ($row['has_is_active']) $rules['is_active'] = ['required', Rule::in(['true', 'false'])];
            try {
                $this->rows->validate($row, $rules);
            } catch (ValidationException $e) {
                $results[] = $this->rows->error($n, $row['original'], $label, $e->validator->errors()->all());
                continue;
            }
            $payload = [];
            if ($row['has_display_name']) $payload['display_name'] = $row['display_name'] === '' ? null : $row['display_name'];
            if ($row['has_is_active']) $payload['is_active'] = $row['is_active'] === 'true';
            if ($row['season_pair']) {
                $payload['external_provider'] = $row['season_club_provider'];
                $payload['external_id'] = $row['season_club_external_id'];
            } elseif ($row['has_season_pair']) {
                $payload['external_provider'] = null;
                $payload['external_id'] = null;
            }
            if (! $model) $payload += ['is_active' => true];
            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $results[] = ['row_number' => $n, 'data' => $row['original'], 'identifier' => $label, 'action' => !$model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'season_id' => $row['season']->id, 'real_club_id' => $row['club']->id];
        }
        return $this->rows->summarize($results);
    }

    private function prepare(array $rows): array
    {
        return array_map(function ($row) {
            $d = $row['data'];
            $original = $d;
            foreach ($d as $key => $value) if (! in_array($key, ['club_external_id', 'season_club_external_id'], true)) $d[$key] = trim($value);
            $d['competition_code'] = RealCompetition::normalizeCode(
                $d['competition_code'],
            );
            foreach (['club_provider', 'season_club_provider'] as $field) if (isset($d[$field])) $d[$field] = mb_strtolower($d[$field]);
            if (isset($d['club_slug'])) $d['club_slug'] = str($d['club_slug'])->slug()->lower()->toString();
            return $d + ['row_number' => $row['row_number'], 'original' => $original, 'club_provider' => '', 'club_external_id' => '', 'club_slug' => '', 'season_club_provider' => '', 'season_club_external_id' => '', 'display_name' => '', 'is_active' => '', 'club_pair' => filled($d['club_provider'] ?? '') && filled($d['club_external_id'] ?? '') ? ($d['club_provider'] . "\0" . $d['club_external_id']) : '', 'season_pair' => filled($d['season_club_provider'] ?? '') && filled($d['season_club_external_id'] ?? '') ? ($d['season_club_provider'] . "\0" . $d['season_club_external_id']) : '', 'has_display_name' => ($d['display_name'] ?? '') !== '', 'has_is_active' => ($d['is_active'] ?? '') !== '', 'has_season_pair' => array_key_exists('season_club_provider', $d) && array_key_exists('season_club_external_id', $d)];
        }, $rows);
    }

    private function referenceError(array $row): ?string
    {
        if (! $row['season']) return 'Unknown season.';
        if (($row['club_provider'] === '') xor ($row['club_external_id'] === '')) return 'club_provider and club_external_id must be supplied together.';
        if ($row['club_provider'] === '' && $row['club_slug'] === '') return 'Supply a RealClub provider identity or club_slug.';
        if ($row['by_pair'] && $row['by_slug'] && $row['by_pair']->id !== $row['by_slug']->id) return 'Provider identity and club_slug resolve to different RealClubs.';
        if (! $row['club']) return 'Unknown RealClub.';
        if (($row['season_club_provider'] === '') xor ($row['season_club_external_id'] === '')) return 'season_club_provider and season_club_external_id must be supplied together.';
        return null;
    }

    private function duplicateRows(array $rows, callable $identity): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $identity($row);
            if ($key) $groups[$key][] = $row['row_number'];
        }
        $duplicates = [];
        foreach ($groups as $numbers) if (count($numbers) > 1) foreach ($numbers as $n) $duplicates[$n] = array_values(array_diff($numbers, [$n]));
        return $duplicates;
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $model = SeasonClub::where('season_id', $row['season_id'])->where('real_club_id', $row['real_club_id'])->first();
            if (($row['model_id'] ?? null) !== $model?->id) throw new \RuntimeException("SeasonClub identity changed since analysis at CSV row {$row['row_number']}.");
            $model ? $model->fill($row['payload'])->save() : SeasonClub::create(['season_id' => $row['season_id'], 'real_club_id' => $row['real_club_id']] + $row['payload']);
        }
    }
}
