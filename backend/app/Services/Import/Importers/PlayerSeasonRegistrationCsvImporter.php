<?php

namespace App\Services\Import\Importers;

use App\Models\PlayerExternalIdentity;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClubExternalIdentity;
use App\Models\RealCompetition;
use App\Models\Season;
use App\Models\SeasonClub;
use App\Services\Import\Importers\CsvImporter;
use App\Services\Import\ImportRowAnalysis;
use App\Services\Import\RecoverableRowException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlayerSeasonRegistrationCsvImporter implements CsvImporter
{
    public function __construct(private ImportRowAnalysis $rows) {}

    public function contract(): array
    {
        return [
            'columns' => ['competition_code', 'season_name', 'player_provider', 'player_external_id', 'club_provider', 'club_external_id', 'registration_provider', 'registration_external_id', 'player_role', 'quotation', 'shirt_number', 'registered_on', 'released_on', 'is_active'],
            'required_header' => ['competition_code', 'season_name', 'player_provider', 'player_external_id', 'club_provider', 'club_external_id'],
            'identifier' => 'registration_provider + registration_external_id when supplied; otherwise resolved Player + SeasonClub',
            'required_create' => ['competition_code', 'season_name', 'player_provider', 'player_external_id', 'club_provider', 'club_external_id', 'player_role'],
            'optional' => ['registration_provider + registration_external_id', 'quotation', 'shirt_number', 'registered_on', 'released_on', 'is_active'],
            'formats' => ['providers: trimmed and lowercase', 'external IDs: opaque', 'quotation: decimal (maximum 999999.99, two decimal places)', 'shirt_number: integer 0–65535', 'registered_on / released_on: YYYY-MM-DD', 'is_active: true or false'],
            'behavior' => 'Creates or updates one player registration for the resolved season club. Only non-empty optional cells are supplied; empty cells preserve existing values, and creates use database defaults. Direct registration identity is preferred and must agree with the player and season club.',
            'dependency' => 'The competition, season, player external identity, club external identity, season club, and player role key must already exist. Import after those records.',
            'example' => ['serie_a', '2026/27', 'opta', 'Player-001', 'opta', 'Club-001', 'opta', 'Registration-001', 'forward', '25.50', '9', '2026-08-20', '', 'true'],
            'caveats' => ['Competition codes use canonical lowercase snake_case normalization. Names never identify players or clubs.', 'Quotation retains decimal(8,2) precision. Empty quotation and other optional cells do not clear or zero values.', 'Released registrations are historical and are never repurposed for another club. A new active registration is rejected while another active registration exists for that player in the Season; release it explicitly in a separate import first.', 'Values must be calendar dates in YYYY-MM-DD format; datetime values are rejected. Empty nullable cells mean leave unchanged, not clear.'],
        ];
    }

    public function analyse(array $csv): array
    {
        $prepared = array_map(fn(array $row): array => $this->prepare($row), $csv['rows']);
        $competitions = RealCompetition::whereIn('code', array_unique(array_column($prepared, 'competition_code')))->get()->keyBy('code');
        $seasons = Season::whereIn('real_competition_id', $competitions->pluck('id'))->whereIn('name', array_unique(array_column($prepared, 'season_name')))->get()->keyBy(fn(Season $season): string => $season->real_competition_id . "\0" . $season->name);
        $players = $this->playerIdentities($prepared);
        $clubs = $this->clubIdentities($prepared);
        $roles = PlayerRole::whereIn('key', array_filter(array_unique(array_column($prepared, 'player_role'))))->get()->keyBy('key');

        foreach ($prepared as &$row) {
            $competition = $competitions->get($row['competition_code']);
            $row['competition'] = $competition;
            $row['season'] = $competition ? $seasons->get($competition->id . "\0" . $row['season_name']) : null;
            $row['player'] = $players->get($row['player_pair'])?->player;
            $row['club'] = $clubs->get($row['club_pair'])?->realClub;
            $row['role'] = $roles->get($row['player_role']);
        }
        unset($row);

        $seasonClubs = SeasonClub::whereIn('season_id', collect($prepared)->pluck('season.id')->filter()->unique())
            ->whereIn('real_club_id', collect($prepared)->pluck('club.id')->filter()->unique())
            ->get()->keyBy(fn(SeasonClub $club): string => $club->season_id . "\0" . $club->real_club_id);
        foreach ($prepared as &$row) {
            $row['season_club'] = $row['season'] && $row['club'] ? $seasonClubs->get($row['season']->id . "\0" . $row['club']->id) : null;
        }
        unset($row);

        $registrations = PlayerSeasonRegistration::with('seasonClub:id,season_id')
            ->where(function ($query) use ($prepared): void {
                $playerIds = collect($prepared)->pluck('player.id')->filter()->unique();
                $seasonClubIds = collect($prepared)->pluck('season_club.id')->filter()->unique();
                $pairs = array_filter(array_unique(array_column($prepared, 'registration_pair')));
                $query->where(fn($natural) => $natural->whereIn('player_id', $playerIds)->whereIn('season_club_id', $seasonClubIds));
                foreach ($pairs as $pair) {
                    [$provider, $externalId] = explode("\0", $pair, 2);
                    $query->orWhere(fn($direct) => $direct->where('external_provider', $provider)->where('external_id', $externalId));
                }
            })->get();
        $natural = $registrations->keyBy(fn(PlayerSeasonRegistration $registration): string => $registration->player_id . "\0" . $registration->season_club_id);
        $direct = $registrations->filter(fn($registration) => filled($registration->external_provider))->keyBy(fn($registration): string => $registration->external_provider . "\0" . $registration->external_id);
        $activeByPlayerSeason = PlayerSeasonRegistration::query()->activeForSeasonIds(collect($prepared)->pluck('season.id')->filter()->unique()->all())
            ->whereIn('player_id', collect($prepared)->pluck('player.id')->filter()->unique())->with('seasonClub:id,season_id')->get()
            ->groupBy(fn($registration): string => $registration->player_id . "\0" . $registration->seasonClub->season_id);
        $naturalDuplicates = $this->duplicateRows(
            $prepared,
            fn(array $row): ?string => $row['player'] && $row['season_club']
                ? $row['player']->id . "\0" . $row['season_club']->id
                : null,
        );
        $directDuplicates = $this->duplicateRows(
            $prepared,
            fn(array $row): ?string => $row['registration_pair'] ?: null,
        );
        $results = [];

        foreach ($prepared as $row) {
            $results[] = $this->analyseRow($row, $natural, $direct, $activeByPlayerSeason, $naturalDuplicates, $directDuplicates);
        }

        return $this->rows->summarize($results);
    }

    private function analyseRow(array $row, Collection $natural, Collection $direct, Collection $active, array $naturalDuplicates, array $directDuplicates): array
    {
        $number = $row['row_number'];
        $label = $row['registration_pair'] ?: $row['competition_code'] . ' / ' . $row['season_name'] . ' / ' . $row['player_provider'] . ':' . $row['player_external_id'] . ' / ' . $row['club_provider'] . ':' . $row['club_external_id'];
        if ($error = $this->referenceError($row)) return $this->rows->error($number, $row['original'], $label, $error);
        if (isset($naturalDuplicates[$number])) return $this->rows->error($number, $row['original'], $label, 'The same player and season club also appear on CSV row ' . implode(', ', $naturalDuplicates[$number]) . '.');
        if (isset($directDuplicates[$number])) return $this->rows->error($number, $row['original'], $label, 'The same registration provider identity also appears on CSV row ' . implode(', ', $directDuplicates[$number]) . '.');

        $naturalModel = $natural->get($row['player']->id . "\0" . $row['season_club']->id);
        $directModel = $row['registration_pair'] ? $direct->get($row['registration_pair']) : null;
        if ($directModel && ($directModel->id !== $naturalModel?->id || $directModel->player_id !== $row['player']->id || $directModel->season_club_id !== $row['season_club']->id)) {
            return $this->rows->error($number, $row['original'], $label, 'Registration external identity conflicts with the resolved player, season, or club.');
        }
        $model = $directModel ?: $naturalModel;

        try {
            $rules = [];
            if (! $model || $row['has_player_role']) $rules['player_role'] = ['required', 'string', 'max:255'];
            if ($row['has_quotation']) $rules['quotation'] = ['numeric', 'decimal:0,2', 'between:0,999999.99'];
            if ($row['has_shirt_number']) $rules['shirt_number'] = ['integer', 'between:0,65535'];
            if ($row['has_is_active']) $rules['is_active'] = [Rule::in(['true', 'false'])];
            foreach (['registered_on', 'released_on'] as $field) if ($row['has_' . $field]) $rules[$field] = ['date_format:Y-m-d'];
            $this->rows->validate($row, $rules);
        } catch (ValidationException $exception) {
            return $this->rows->error($number, $row['original'], $label, $exception->validator->errors()->all());
        }

        $payload = [];
        if ($row['has_player_role']) {
            if (! $row['role']) return $this->rows->error($number, $row['original'], $label, 'Unknown player_role key.');
            $payload['player_role_id'] = $row['role']->id;
        }
        if ($row['registration_pair']) [$payload['external_provider'], $payload['external_id']] = [$row['registration_provider'], $row['registration_external_id']];
        foreach (['quotation', 'shirt_number', 'is_active', 'registered_on', 'released_on'] as $field) {
            if ($row['has_' . $field]) {
                $payload[$field] = $this->payloadValue($field, $row[$field]);
            }
        }
        if (! $model) $payload += ['is_active' => true];

        $willBeActive = ($payload['is_active'] ?? $model?->is_active ?? true) && ($payload['released_on'] ?? $model?->released_on) === null;
        $otherActive = $active->get($row['player']->id . "\0" . $row['season']->id, collect())->first(fn($registration) => $registration->id !== $model?->id);
        if ($willBeActive && $otherActive) return $this->rows->error($number, $row['original'], $label, 'Player already has another active registration in this Season. Release it before importing a transfer.');

        $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
        return ['row_number' => $number, 'data' => $row['original'], 'identifier' => $label, 'action' => ! $model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'competition_id' => $row['competition']->id, 'competition_code' => $row['competition_code'], 'season_id' => $row['season']->id, 'season_name' => $row['season_name'], 'player_id' => $row['player']->id, 'season_club_id' => $row['season_club']->id, 'player_pair' => $row['player_pair'], 'club_pair' => $row['club_pair'], 'registration_pair' => $row['registration_pair'], 'player_role_key' => $row['has_player_role'] ? $row['player_role'] : null];
    }

    public function execute(array $analysis): void
    {
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $playerIdentity = PlayerExternalIdentity::where('provider', explode("\0", $row['player_pair'], 2)[0])->where('external_id', explode("\0", $row['player_pair'], 2)[1])->first();
            $clubIdentity = RealClubExternalIdentity::where('provider', explode("\0", $row['club_pair'], 2)[0])->where('external_id', explode("\0", $row['club_pair'], 2)[1])->first();
            $competition = RealCompetition::whereKey($row['competition_id'])->where('code', $row['competition_code'])->first();
            $season = Season::whereKey($row['season_id'])->where('real_competition_id', $row['competition_id'])->where('name', $row['season_name'])->first();
            $seasonClub = SeasonClub::find($row['season_club_id']);
            $role = $row['player_role_key'] ? PlayerRole::where('key', $row['player_role_key'])->first() : null;
            if (! $competition || ! $season || $playerIdentity?->player_id !== $row['player_id'] || $clubIdentity?->real_club_id !== $seasonClub?->real_club_id || $seasonClub?->season_id !== $row['season_id'] || ($row['player_role_key'] && $role?->id !== $row['payload']['player_role_id'])) throw new RecoverableRowException("Registration dependencies changed since analysis at CSV row {$row['row_number']}.");
            $natural = PlayerSeasonRegistration::where('player_id', $row['player_id'])->where('season_club_id', $row['season_club_id'])->first();
            $direct = null;
            if ($row['registration_pair']) {
                [$provider, $externalId] = explode("\0", $row['registration_pair'], 2);
                $direct = PlayerSeasonRegistration::where('external_provider', $provider)->where('external_id', $externalId)->first();
            }
            $model = $direct ?: $natural;
            if (($row['model_id'] ?? null) !== $model?->id || ($direct && $direct->id !== $natural?->id)) throw new RecoverableRowException("Registration identity changed since analysis at CSV row {$row['row_number']}.");
            $willBeActive = ($row['payload']['is_active'] ?? $model?->is_active ?? true) && ($row['payload']['released_on'] ?? $model?->released_on) === null;
            if ($willBeActive && PlayerSeasonRegistration::query()->activeForSeason($row['season_id'])->where('player_id', $row['player_id'])->when($model, fn($query) => $query->where('id', '!=', $model->id))->exists()) throw new RecoverableRowException("Player gained another active registration since analysis at CSV row {$row['row_number']}.");
            $model ? $model->fill($row['payload'])->save() : PlayerSeasonRegistration::create(['player_id' => $row['player_id'], 'season_club_id' => $row['season_club_id']] + $row['payload']);
        }
    }

    private function prepare(array $row): array
    {
        $data = $row['data'];
        $original = $data;
        foreach ($data as $field => $value) if (! str_ends_with($field, 'external_id')) $data[$field] = trim($value);
        $data['competition_code'] = RealCompetition::normalizeCode($data['competition_code']);
        foreach (['player_provider', 'club_provider', 'registration_provider'] as $field) if (isset($data[$field])) $data[$field] = mb_strtolower($data[$field]);
        foreach (['player_role'] as $field) if (isset($data[$field])) $data[$field] = mb_strtolower($data[$field]);
        $data += ['registration_provider' => '', 'registration_external_id' => '', 'player_role' => '', 'quotation' => '', 'shirt_number' => '', 'registered_on' => '', 'released_on' => '', 'is_active' => ''];
        foreach (['player_role', 'quotation', 'shirt_number', 'registered_on', 'released_on', 'is_active'] as $field) $data['has_' . $field] = array_key_exists($field, $row['data']) && $data[$field] !== '';
        $data['player_pair'] = $data['player_provider'] . "\0" . $data['player_external_id'];
        $data['club_pair'] = $data['club_provider'] . "\0" . $data['club_external_id'];
        $data['registration_pair'] = filled($data['registration_provider']) && filled($data['registration_external_id']) ? $data['registration_provider'] . "\0" . $data['registration_external_id'] : '';
        return $data + ['row_number' => $row['row_number'], 'original' => $original];
    }

    private function referenceError(array $row): ?string
    {
        if (! $row['competition']) return 'Unknown competition_code.';
        if (! $row['season']) return 'Unknown season.';
        if (! filled($row['player_provider']) || ! filled($row['player_external_id'])) return 'player_provider and player_external_id are required.';
        if (! $row['player']) return 'Unknown player external identity.';
        if (! filled($row['club_provider']) || ! filled($row['club_external_id'])) return 'club_provider and club_external_id are required.';
        if (! $row['club']) return 'Unknown club external identity.';
        if (! $row['season_club']) return 'The resolved club is not registered for the target season.';
        if ((filled($row['registration_provider'])) xor (filled($row['registration_external_id']))) return 'registration_provider and registration_external_id must be supplied together.';
        return null;
    }

    private function playerIdentities(array $rows): Collection
    {
        return $this->identityQuery(PlayerExternalIdentity::query()->with('player'), array_column($rows, 'player_pair'));
    }

    private function clubIdentities(array $rows): Collection
    {
        return $this->identityQuery(RealClubExternalIdentity::query()->with('realClub'), array_column($rows, 'club_pair'));
    }

    private function identityQuery(Builder $query, array $pairs): Collection
    {
        $query->where(function (Builder $query) use ($pairs): void {
            foreach (array_unique($pairs) as $pair) {
                [$provider, $externalId] = explode("\0", $pair, 2);

                $query->orWhere(
                    fn(Builder $pairQuery) => $pairQuery
                        ->where('provider', $provider)
                        ->where('external_id', $externalId),
                );
            }
        });

        return $query
            ->get()
            ->keyBy(
                fn($identity): string =>
                $identity->provider . "\0" . $identity->external_id,
            );
    }

    private function duplicateRows(array $rows, callable $identity): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $identity($row);
            if ($key) $groups[$key][] = $row['row_number'];
        }
        $duplicates = [];
        foreach ($groups as $numbers) if (count($numbers) > 1) foreach ($numbers as $number) $duplicates[$number] = array_values(array_diff($numbers, [$number]));
        return $duplicates;
    }

    private function payloadValue(string $field, string $value): mixed
    {
        return match ($field) {
            'is_active' => $value === 'true',
            'shirt_number' => (int) $value,
            'quotation' => number_format((float) $value, 2, '.', ''),
            default => $value,
        };
    }
}
