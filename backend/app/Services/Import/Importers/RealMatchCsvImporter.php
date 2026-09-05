<?php

namespace App\Services\Import\Importers;

use App\Enums\RealMatchStatus;
use App\Models\Matchday;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\RealMatch;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\ImportRowAnalysis;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\Import\RecoverableRowException;

class RealMatchCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        return [
            'columns' => ['competition_code', 'season_name', 'matchday_number', 'home_club_provider', 'home_club_external_id', 'away_club_provider', 'away_club_external_id', 'kickoff_at', 'home_score', 'away_score', 'status'],
            'required_header' => ['competition_code', 'season_name', 'matchday_number', 'home_club_provider', 'home_club_external_id', 'away_club_provider', 'away_club_external_id'],
            'identifier' => 'matchday + resolved home SeasonClub + resolved away SeasonClub',
            'required_create' => ['competition_code', 'season_name', 'matchday_number', 'home club identity', 'away club identity', 'kickoff_at', 'status'],
            'optional' => ['home_score', 'away_score'],
            'formats' => ['providers: trim + lowercase', 'external IDs: opaque', 'kickoff_at: ISO-8601 datetime with explicit offset', 'scores: integer 0–65535', 'status: ' . implode(', ', array_column(RealMatchStatus::cases(), 'value'))],
            'behavior' => 'Updates only supplied mutable fields. Empty mutable cells preserve stored values.',
            'dependency' => 'Competition, Season, Matchday, RealClub identities, and SeasonClubs must already exist.',
            'example' => ['serie_a', '2026/27', '1', 'opta', 'Club-Home', 'opta', 'Club-Away', '2026-08-22T20:45:00+02:00', '2', '1', RealMatchStatus::Scheduled->value],
            'caveats' => ['No RealMatch external identity exists. CSV cannot clear a nullable score because there is no null sentinel.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = [];
        $codes = [];
        $pairs = [];
        foreach ($csv['rows'] as $row) {
            $d = $row['data'];
            $d['competition_code'] = RealCompetition::normalizeCode($d['competition_code']);
            foreach (['season_name', 'matchday_number', 'home_club_provider', 'away_club_provider', 'kickoff_at', 'home_score', 'away_score', 'status'] as $field) {
                if (array_key_exists($field, $d)) $d[$field] = trim($d[$field]);
            }
            foreach (['home_club_provider', 'away_club_provider'] as $field) $d[$field] = mb_strtolower($d[$field] ?? '');
            $d += ['kickoff_at' => '', 'home_score' => '', 'away_score' => '', 'status' => ''];
            $d['home_pair'] = $d['home_club_provider'] . "\0" . ($d['home_club_external_id'] ?? '');
            $d['away_pair'] = $d['away_club_provider'] . "\0" . ($d['away_club_external_id'] ?? '');
            $codes[] = $d['competition_code'];
            $pairs[] = $d['home_pair'];
            $pairs[] = $d['away_pair'];
            $prepared[] = $row + ['normalized' => $d];
        }

        $competitions = RealCompetition::whereIn('code', array_unique($codes))->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', array_unique(array_map(fn($row) => $row['normalized']['season_name'], $prepared)))->get()->keyBy(fn($season) => $season->real_competition_id . "\0" . $season->name);
        $matchdays = Matchday::whereIn('season_id', $seasons->pluck('id'))->whereIn('number', array_unique(array_map(fn($row) => $row['normalized']['matchday_number'], $prepared)))->get()->keyBy(fn($matchday) => $matchday->season_id . "\0" . $matchday->number);
        $identities = RealClubExternalIdentity::where(function ($query) use ($pairs) {
            foreach (array_unique($pairs) as $pair) {
                [$provider, $externalId] = explode("\0", $pair, 2);
                $query->orWhere(fn($identity) => $identity->where('provider', $provider)->where('external_id', $externalId));
            }
        })->get()->keyBy(fn($identity) => $identity->provider . "\0" . $identity->external_id);
        $seasonClubs = SeasonClub::whereIn('season_id', $seasons->pluck('id'))->whereIn('real_club_id', $identities->pluck('real_club_id'))->get()->keyBy(fn($club) => $club->season_id . "\0" . $club->real_club_id);

        foreach ($prepared as &$row) {
            $d = $row['normalized'];
            $competition = $competitions->get($d['competition_code']);
            $row['competition'] = $competition;
            $row['season'] = $competition ? $seasons->get($competition->id . "\0" . $d['season_name']) : null;
            $row['matchday'] = $row['season'] ? $matchdays->get($row['season']->id . "\0" . $d['matchday_number']) : null;
            $row['home_identity'] = $identities->get($d['home_pair']);
            $row['away_identity'] = $identities->get($d['away_pair']);
            $row['home_club'] = $row['season'] && $row['home_identity'] ? $seasonClubs->get($row['season']->id . "\0" . $row['home_identity']->real_club_id) : null;
            $row['away_club'] = $row['season'] && $row['away_identity'] ? $seasonClubs->get($row['season']->id . "\0" . $row['away_identity']->real_club_id) : null;
            $row['identity'] = $row['matchday'] && $row['home_club'] && $row['away_club'] ? $row['matchday']->id . "\0" . $row['home_club']->id . "\0" . $row['away_club']->id : null;
        }
        unset($row);

        $identityGroups = [];
        foreach ($prepared as $row) if ($row['identity']) $identityGroups[$row['identity']][] = $row['row_number'];
        $matchdayIds = collect($prepared)->pluck('matchday.id')->filter()->unique();
        $existing = RealMatch::whereIn('matchday_id', $matchdayIds)->get()->keyBy(fn($match) => $match->matchday_id . "\0" . $match->home_season_club_id . "\0" . $match->away_season_club_id);
        $results = [];

        foreach ($prepared as $row) {
            $d = $row['normalized'];
            $n = $row['row_number'];
            $label = $d['competition_code'] . ' / ' . $d['season_name'] . ' / ' . $d['matchday_number'];
            $error = $this->referenceError($row);
            if (! $error && count($identityGroups[$row['identity']]) > 1) $error = 'Duplicate RealMatch identity also appears on CSV row ' . implode(', ', array_diff($identityGroups[$row['identity']], [$n])) . '.';
            if ($error) {
                $results[] = $this->rows->error($n, $row['data'], $label, $error);
                continue;
            }
            $model = $existing->get($row['identity']);
            $rules = [];
            if (! $model || $d['kickoff_at'] !== '') $rules['kickoff_at'] = ['required', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/'];
            foreach (['home_score', 'away_score'] as $field) if ($d[$field] !== '') $rules[$field] = ['integer', 'between:0,65535'];
            if (! $model || $d['status'] !== '') $rules['status'] = ['required', Rule::enum(RealMatchStatus::class)];
            try {
                $this->rows->validate($d, $rules);
                if ($d['kickoff_at'] !== '') $d['kickoff_at'] = CarbonImmutable::parse($d['kickoff_at'])->utc()->format('Y-m-d H:i:s');
            } catch (ValidationException $exception) {
                $results[] = $this->rows->error($n, $row['data'], $label, $exception->validator->errors()->all());
                continue;
            } catch (\Throwable) {
                $results[] = $this->rows->error($n, $row['data'], $label, 'Invalid ISO-8601 timestamp.');
                continue;
            }
            $payload = array_intersect_key($d, array_flip(['kickoff_at', 'home_score', 'away_score', 'status']));
            $payload = array_filter($payload, static fn(string $value): bool => $value !== '');
            foreach (['home_score', 'away_score'] as $field) if (isset($payload[$field])) $payload[$field] = (int) $payload[$field];
            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $results[] = ['row_number' => $n, 'data' => $row['data'], 'identifier' => $label, 'action' => ! $model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'season_id' => $row['season']->id, 'matchday_id' => $row['matchday']->id, 'home_season_club_id' => $row['home_club']->id, 'away_season_club_id' => $row['away_club']->id];
        }

        return $this->rows->summarize($results);
    }

    private function referenceError(array $row): ?string
    {
        $d = $row['normalized'];
        if (! filled($d['competition_code'])) return 'competition_code is required.';
        if (! filled($d['season_name'])) return 'season_name is required.';
        if (! filled($d['matchday_number'])) return 'matchday_number is required.';
        if (! $row['competition']) return 'Unknown competition_code.';
        if (! $row['season']) return 'Unknown season.';
        if (! $row['matchday']) return 'Unknown matchday.';
        foreach (['home', 'away'] as $side) {
            if ($d[$side . '_club_provider'] === '' || ($d[$side . '_club_external_id'] ?? '') === '') return ucfirst($side) . ' club provider and external ID are required.';
            if (! $row[$side . '_identity']) return 'Unknown ' . $side . ' club identity.';
            if (! $row[$side . '_club']) return ucfirst($side) . ' club does not belong to the resolved Season.';
        }
        if ($row['home_club']->id === $row['away_club']->id) return 'Home and away clubs must be different.';
        return null;
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $matchday = Matchday::find($row['matchday_id']);
            $clubs = SeasonClub::whereKey([$row['home_season_club_id'], $row['away_season_club_id']])->get()->keyBy('id');
            if (! $matchday || $matchday->season_id !== $row['season_id'] || $clubs->count() !== 2 || $clubs->contains(fn($club) => $club->season_id !== $row['season_id'])) throw new RecoverableRowException("RealMatch dependency identity changed since analysis at CSV row {$row['row_number']}.");
            $model = RealMatch::where('matchday_id', $row['matchday_id'])->where('home_season_club_id', $row['home_season_club_id'])->where('away_season_club_id', $row['away_season_club_id'])->first();
            if (($row['model_id'] ?? null) !== $model?->id) throw new RecoverableRowException("RealMatch identity changed since analysis at CSV row {$row['row_number']}.");
            $identity = ['matchday_id' => $row['matchday_id'], 'home_season_club_id' => $row['home_season_club_id'], 'away_season_club_id' => $row['away_season_club_id']];
            $model ? $model->fill($row['payload'])->save() : RealMatch::create($identity + $row['payload']);
        }
    }
}
