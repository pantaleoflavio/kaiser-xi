<?php

namespace App\Services\Import\Importers;

use App\Enums\PlayerScoreStatus;
use App\Models\Matchday;
use App\Models\PlayerExternalIdentity;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\ImportRowAnalysis;
use App\Services\Import\RecoverableRowException;
use App\Services\PlayerScore\PlayerScoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlayerScoreCsvImporter implements CsvImporter
{
    private const BOOLEAN_FIELDS = ['clean_sheet', 'is_captain'];

    private const DECIMAL_FIELDS = ['base_rating', 'final_score'];

    public function __construct(
        private ImportRowAnalysis $rows,
        private PlayerScoreService $scores,
    ) {}

    public function contract(): array
    {
        $events = PlayerScoreService::EVENT_FIELDS;

        return [
            'columns' => ['competition_code', 'season_name', 'matchday_number', 'registration_provider', 'registration_external_id', 'player_provider', 'player_external_id', 'club_provider', 'club_external_id', 'status', 'base_rating', ...$events, 'clean_sheet', 'is_captain', 'final_score'],
            'required_header' => ['competition_code', 'season_name', 'matchday_number', 'status'],
            'identifier' => 'PlayerSeasonRegistration + Matchday. Prefer registration_provider + registration_external_id; otherwise use player and club provider identities in the Season.',
            'required_create' => ['competition_code', 'season_name', 'matchday_number', 'status', 'a complete preferred or fallback registration identity', 'base_rating when status is confirmed'],
            'optional' => ['base_rating', ...$events, 'clean_sheet', 'is_captain', 'final_score for pending scores'],
            'formats' => ['status: ' . implode(', ', array_keys(PlayerScoreStatus::options())), 'providers: trimmed and lowercase', 'external IDs: opaque and exact', 'event fields: non-negative integers', 'clean_sheet / is_captain: true or false', 'base_rating: -99.99 to 99.99, maximum two decimal places', 'final_score: -999.99 to 999.99, maximum two decimal places'],
            'behavior' => 'Player scores are global raw real-football data, not League-specific. League rules score the raw fields. Empty optional cells preserve values on update; creates use PlayerScore defaults. did_not_play clears ratings and score, resets every event to zero, and sets both booleans to false. final_score is optional external/provider data, is never calculated by this import, and does not control League fantasy points.',
            'dependency' => 'Competition, Season, Matchday, and PlayerSeasonRegistration must already exist. The registration and Matchday must belong to the same Season.',
            'example' => ['serie_a', '2026/27', '1', 'opta', 'Registration-001', '', '', '', '', 'confirmed', '7.50', '1', '0', '0', '0', '0', '0', '0', '0', '0', 'true', 'false', '10.00'],
            'caveats' => ['Preferred and fallback registration identities must resolve to the same registration when both are supplied.', 'Changing imported player scores does not automatically change already calculated fantasy matchdays. Recalculate the affected matchday explicitly if historical fantasy results should use the new scores. Commissioners or co-commissioners must initiate that recalculation.', 'CSV import never deletes a player score.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = array_map(fn(array $row): array => $this->prepareRow($row), $csv['rows']);
        $competitions = RealCompetition::whereIn('code', collect($prepared)->pluck('competition_code')->unique())->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', collect($prepared)->pluck('season_name')->unique())->get()->keyBy(fn(Season $season): string => $season->real_competition_id . "\0" . $season->name);

        foreach ($prepared as &$row) {
            $row['competition'] = $competitions->get($row['competition_code']);
            $row['season'] = $row['competition'] ? $seasons->get($row['competition']->id . "\0" . $row['season_name']) : null;
        }
        unset($row);

        $matchdays = Matchday::whereIn('season_id', collect($prepared)->pluck('season.id')->filter()->unique())->whereIn('number', collect($prepared)->pluck('matchday_number')->unique())->get()->keyBy(fn(Matchday $matchday): string => $matchday->season_id . "\0" . $matchday->number);
        $direct = $this->directRegistrations($prepared);
        $players = $this->identities(PlayerExternalIdentity::query(), array_filter(array_column($prepared, 'player_pair')));
        $clubs = $this->identities(RealClubExternalIdentity::query(), array_filter(array_column($prepared, 'club_pair')));

        foreach ($prepared as &$row) {
            $row['matchday'] = $row['season'] ? $matchdays->get($row['season']->id . "\0" . $row['matchday_number']) : null;
            $row['direct_registration'] = $row['registration_pair'] ? $direct->get($row['registration_pair']) : null;
            $row['player_id'] = $row['player_pair'] ? $players->get($row['player_pair'])?->player_id : null;
            $row['club_id'] = $row['club_pair'] ? $clubs->get($row['club_pair'])?->real_club_id : null;
        }
        unset($row);

        $seasonClubs = SeasonClub::whereIn('season_id', collect($prepared)->pluck('season.id')->filter()->unique())->whereIn('real_club_id', collect($prepared)->pluck('club_id')->filter()->unique())->get()->keyBy(fn(SeasonClub $club): string => $club->season_id . "\0" . $club->real_club_id);
        foreach ($prepared as &$row) {
            $row['season_club'] = $row['season'] && $row['club_id'] ? $seasonClubs->get($row['season']->id . "\0" . $row['club_id']) : null;
        }
        unset($row);

        $fallback = PlayerSeasonRegistration::with('seasonClub:id,season_id')->whereIn('player_id', collect($prepared)->pluck('player_id')->filter()->unique())->whereIn('season_club_id', collect($prepared)->pluck('season_club.id')->filter()->unique())->get()->keyBy(fn(PlayerSeasonRegistration $registration): string => $registration->player_id . "\0" . $registration->season_club_id);
        foreach ($prepared as &$row) {
            $row['fallback_registration'] = $row['player_id'] && $row['season_club'] ? $fallback->get($row['player_id'] . "\0" . $row['season_club']->id) : null;
            $row['registration'] = $row['direct_registration'] ?: $row['fallback_registration'];
        }
        unset($row);

        $existing = PlayerScore::whereIn('player_season_registration_id', collect($prepared)->pluck('registration.id')->filter()->unique())->whereIn('matchday_id', collect($prepared)->pluck('matchday.id')->filter()->unique())->get()->keyBy(fn(PlayerScore $score): string => $score->player_season_registration_id . "\0" . $score->matchday_id);
        $duplicates = $this->duplicates($prepared);
        $results = [];
        foreach ($prepared as $row) $results[] = $this->analyseRow($row, $existing, $duplicates);

        return $this->rows->summarize($results);
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if ($row['action'] === 'unmatched') {
                [$provider, $externalId] = explode("\0", $row['data']['player_provider'] . "\0" . $row['data']['player_external_id'], 2);
                if (PlayerExternalIdentity::where('provider', mb_strtolower(trim($provider)))->where('external_id', $externalId)->exists()) {
                    throw new RecoverableRowException("Player score identity changed since analysis at CSV row {$row['row_number']}.");
                }

                continue;
            }
            if (! in_array($row['action'], ['create', 'update'], true)) continue;

            $competition = RealCompetition::whereKey($row['competition_id'])->where('code', $row['competition_code'])->first();
            $season = Season::whereKey($row['season_id'])->where('real_competition_id', $row['competition_id'])->where('name', $row['season_name'])->first();
            $matchday = Matchday::whereKey($row['matchday_id'])->where('season_id', $row['season_id'])->where('number', $row['matchday_number'])->first();
            $direct = $this->resolveDirect($row['registration_pair']);
            $fallback = $this->resolveFallback($row);
            $registration = $direct ?: $fallback;
            $model = PlayerScore::where('player_season_registration_id', $row['registration_id'])->where('matchday_id', $row['matchday_id'])->first();

            if (! $competition || ! $season || ! $matchday || $registration?->id !== $row['registration_id'] || ($direct && $fallback && $direct->id !== $fallback->id) || $registration?->seasonClub?->season_id !== $row['season_id'] || $model?->id !== ($row['model_id'] ?? null)) {
                throw new RecoverableRowException("Player score identity changed since analysis at CSV row {$row['row_number']}.");
            }

            $data = $this->scores->prepare(['player_season_registration_id' => $row['registration_id'], 'matchday_id' => $row['matchday_id']] + $row['payload'], $model);
            $model ? $model->fill($data)->save() : PlayerScore::create($data);
        }
    }

    private function analyseRow(array $row, Collection $existing, array $duplicates): array
    {
        $number = $row['row_number'];
        $label = $row['competition_code'] . ' / ' . $row['season_name'] . ' / ' . $row['matchday_number'] . ' / ' . ($row['registration_pair'] ?: $row['player_pair'] . ' / ' . $row['club_pair']);
        if ($error = $this->referenceError($row)) return $this->rows->error($number, $row['original'], $label, $error);
        if ($this->hasUnmatchedPlayer($row)) {
            if ($error = $this->performanceError($row)) return $this->rows->error($number, $row['original'], $label, $error);

            return [
                'row_number' => $number,
                'data' => $row['original'],
                'identifier' => $label,
                'action' => 'unmatched',
                'changes' => [],
                'warnings' => ['Skipped because the Player external identity is unknown. Create the Player and its external identity, then import this row again.'],
                'errors' => [],
            ];
        }
        if ($row['direct_registration'] && $row['fallback_complete'] && $row['direct_registration']->id !== $row['fallback_registration']?->id) return $this->rows->error($number, $row['original'], $label, 'Direct registration identity conflicts with the fallback player and club identity.');
        if ($row['registration']->seasonClub?->season_id !== $row['season']->id) return $this->rows->error($number, $row['original'], $label, 'The registration and Matchday belong to different Seasons.');
        if (isset($duplicates[$number])) return $this->rows->error($number, $row['original'], $label, 'Duplicate PlayerScore identity also appears on CSV row ' . implode(', ', $duplicates[$number]) . '.');

        $model = $existing->get($row['registration']->id . "\0" . $row['matchday']->id);
        $supplied = array_intersect_key($row, array_flip(['status', 'base_rating', ...PlayerScoreService::EVENT_FIELDS, ...self::BOOLEAN_FIELDS, 'final_score']));
        $supplied = array_filter($supplied, fn(mixed $value, string $field): bool => $row['has_' . $field], ARRAY_FILTER_USE_BOTH);

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (! array_key_exists($field, $supplied)) {
                continue;
            }

            if (! in_array($supplied[$field], ['true', 'false'], true)) {
                return $this->rows->error(
                    $number,
                    $row['original'],
                    $label,
                    "{$field} must be true or false.",
                );
            }

            $supplied[$field] = $supplied[$field] === 'true';
        }
        try {
            $normalized = $this->scores->preparePerformance($supplied, $model);
        } catch (ValidationException $exception) {
            return $this->rows->error($number, $row['original'], $label, $exception->validator->errors()->all());
        }

        $payload = array_intersect_key($normalized, array_flip(['status', 'base_rating', ...PlayerScoreService::EVENT_FIELDS, ...self::BOOLEAN_FIELDS, 'final_score']));
        if ($model && ($supplied['status'] ?? null) !== PlayerScoreStatus::DidNotPlay->value) $payload = array_intersect_key($payload, $supplied);
        $changes = $model ? $this->changedFields($model, $payload) : array_keys($payload);
        $warning = in_array($payload['status'] ?? $model?->status?->value, [PlayerScoreStatus::Confirmed->value], true) && (! $model || $changes)
            ? ['Changing imported player scores does not automatically change already calculated fantasy matchdays. Recalculate affected matchdays explicitly.'] : [];

        return ['row_number' => $number, 'data' => $row['original'], 'identifier' => $label, 'action' => ! $model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => $warning, 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'competition_id' => $row['competition']->id, 'competition_code' => $row['competition_code'], 'season_id' => $row['season']->id, 'season_name' => $row['season_name'], 'matchday_id' => $row['matchday']->id, 'matchday_number' => (int) $row['matchday_number'], 'registration_id' => $row['registration']->id, 'registration_pair' => $row['registration_pair'], 'player_pair' => $row['player_pair'], 'club_pair' => $row['club_pair'], 'player_id' => $row['player_id'], 'club_id' => $row['club_id'], 'season_club_id' => $row['season_club']?->id];
    }

    private function prepareRow(array $row): array
    {
        $data = $row['data'];
        $original = $data;
        foreach ($data as $field => $value) if (! str_ends_with($field, 'external_id')) $data[$field] = trim($value);
        $data['competition_code'] = RealCompetition::normalizeCode($data['competition_code']);
        foreach (['registration_provider', 'player_provider', 'club_provider'] as $field) if (isset($data[$field])) $data[$field] = mb_strtolower($data[$field]);
        $fields = ['registration_provider', 'registration_external_id', 'player_provider', 'player_external_id', 'club_provider', 'club_external_id', 'status', 'base_rating', ...PlayerScoreService::EVENT_FIELDS, ...self::BOOLEAN_FIELDS, 'final_score'];
        foreach ($fields as $field) {
            $data[$field] ??= '';
            $data['has_' . $field] = array_key_exists($field, $row['data']) && $data[$field] !== '';
        }
        $data['registration_pair'] = $data['registration_provider'] !== '' && $data['registration_external_id'] !== '' ? $data['registration_provider'] . "\0" . $data['registration_external_id'] : '';
        $data['player_pair'] = $data['player_provider'] !== '' && $data['player_external_id'] !== '' ? $data['player_provider'] . "\0" . $data['player_external_id'] : '';
        $data['club_pair'] = $data['club_provider'] !== '' && $data['club_external_id'] !== '' ? $data['club_provider'] . "\0" . $data['club_external_id'] : '';
        $data['fallback_complete'] = $data['player_pair'] !== '' && $data['club_pair'] !== '';

        return $data + ['row_number' => $row['row_number'], 'original' => $original];
    }

    private function referenceError(array $row): ?string
    {
        if (! $row['competition']) return 'Unknown competition_code.';
        if (! $row['season']) return 'Unknown Season.';
        if (! $row['matchday']) return 'Unknown Matchday.';
        if (($row['registration_provider'] !== '') xor ($row['registration_external_id'] !== '')) return 'registration_provider and registration_external_id must be supplied together.';
        foreach ([['player_provider', 'player_external_id'], ['club_provider', 'club_external_id']] as [$a, $b]) if (($row[$a] !== '') xor ($row[$b] !== '')) return "$a and $b must be supplied together.";
        $anyFallback = $row['player_provider'] !== '' || $row['player_external_id'] !== '' || $row['club_provider'] !== '' || $row['club_external_id'] !== '';
        if ($anyFallback && ! $row['fallback_complete']) return 'Fallback identity requires complete player and club provider identities.';
        if (! $row['registration_pair'] && ! $row['fallback_complete']) return 'Supply either a registration provider identity or complete player and club provider identities.';
        if ($row['registration_pair'] && ! $row['direct_registration']) return 'Unknown registration external identity.';
        if ($row['fallback_complete'] && ! $row['club_id']) return 'Unknown RealClub external identity.';
        if ($row['fallback_complete'] && ! $row['player_id'] && $row['direct_registration']) return 'Unknown Player external identity.';
        if ($row['fallback_complete'] && ! $row['season_club']) return 'The resolved club is not registered for the target Season.';
        if ($this->hasUnmatchedPlayer($row)) return null;
        if ($row['fallback_complete'] && ! $row['fallback_registration']) return 'No PlayerSeasonRegistration matches the fallback identity.';
        return null;
    }

    private function hasUnmatchedPlayer(array $row): bool
    {
        return ! $row['direct_registration'] && $row['fallback_complete'] && ! $row['player_id'];
    }

    private function performanceError(array $row): array|string|null
    {
        $supplied = array_intersect_key($row, array_flip(['status', 'base_rating', ...PlayerScoreService::EVENT_FIELDS, ...self::BOOLEAN_FIELDS, 'final_score']));
        $supplied = array_filter($supplied, fn(mixed $value, string $field): bool => $row['has_' . $field], ARRAY_FILTER_USE_BOTH);

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (array_key_exists($field, $supplied) && ! in_array($supplied[$field], ['true', 'false'], true)) {
                return "{$field} must be true or false.";
            }

            if (array_key_exists($field, $supplied)) $supplied[$field] = $supplied[$field] === 'true';
        }

        try {
            $this->scores->preparePerformance($supplied);
        } catch (ValidationException $exception) {
            return $exception->validator->errors()->all();
        }

        return null;
    }

    private function directRegistrations(array $rows): Collection
    {
        $pairs = array_filter(array_unique(array_column($rows, 'registration_pair')));
        return $this->pairQuery(PlayerSeasonRegistration::query(), $pairs, 'external_provider')->with('seasonClub:id,season_id')->get()->keyBy(fn($registration): string => $registration->external_provider . "\0" . $registration->external_id);
    }

    private function identities(Builder $query, array $pairs): Collection
    {
        return $this->pairQuery($query, array_unique($pairs), 'provider')->get()->keyBy(fn($identity): string => $identity->provider . "\0" . $identity->external_id);
    }

    private function pairQuery(Builder $query, array $pairs, string $provider): Builder
    {
        if ($pairs === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($pairs, $provider): void {
            foreach ($pairs as $pair) {
                [$source, $externalId] = explode("\0", $pair, 2);
                $query->orWhere(fn(Builder $pairQuery) => $pairQuery->where($provider, $source)->where('external_id', $externalId));
            }
        });
    }

    private function duplicates(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) if ($row['registration'] && $row['matchday']) $groups[$row['registration']->id . "\0" . $row['matchday']->id][] = $row['row_number'];
        $duplicates = [];
        foreach ($groups as $numbers) if (count($numbers) > 1) foreach ($numbers as $number) $duplicates[$number] = array_values(array_diff($numbers, [$number]));
        return $duplicates;
    }

    private function changedFields(PlayerScore $model, array $payload): array
    {
        $changes = [];
        foreach ($payload as $field => $value) {
            $current = $model->getAttribute($field);
            if ($current instanceof \BackedEnum) $current = $current->value;
            if (in_array($field, self::DECIMAL_FIELDS, true)) {
                if ($this->decimal($current) !== $this->decimal($value)) $changes[] = $field;
            } elseif ((string) $current !== (string) $value) $changes[] = $field;
        }
        return $changes;
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = (string) $value;
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $normalized = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        return ($negative && ($normalized !== '0' || $fraction !== '') ? '-' : '') . $normalized . ($fraction !== '' ? '.' . $fraction : '');
    }

    private function resolveDirect(string $pair): ?PlayerSeasonRegistration
    {
        if ($pair === '') return null;
        [$provider, $externalId] = explode("\0", $pair, 2);
        return PlayerSeasonRegistration::with('seasonClub:id,season_id')->where('external_provider', $provider)->where('external_id', $externalId)->first();
    }

    private function resolveFallback(array $row): ?PlayerSeasonRegistration
    {
        if (! $row['player_pair'] || ! $row['club_pair']) return null;
        [$playerProvider, $playerExternalId] = explode("\0", $row['player_pair'], 2);
        [$clubProvider, $clubExternalId] = explode("\0", $row['club_pair'], 2);
        $playerId = PlayerExternalIdentity::where('provider', $playerProvider)->where('external_id', $playerExternalId)->value('player_id');
        $clubId = RealClubExternalIdentity::where('provider', $clubProvider)->where('external_id', $clubExternalId)->value('real_club_id');
        $seasonClub = SeasonClub::whereKey($row['season_club_id'])->where('season_id', $row['season_id'])->where('real_club_id', $clubId)->first();
        if ((int) $playerId !== $row['player_id'] || ! $seasonClub) return null;
        return PlayerSeasonRegistration::with('seasonClub:id,season_id')->where('player_id', $playerId)->where('season_club_id', $seasonClub->id)->first();
    }
}
