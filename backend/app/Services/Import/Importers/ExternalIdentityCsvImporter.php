<?php

namespace App\Services\Import\Importers;

use App\Services\Import\ImportRowAnalysis;
use Illuminate\Validation\ValidationException;

abstract class ExternalIdentityCsvImporter implements CsvImporter
{
    public function __construct(protected ImportRowAnalysis $rows) {}

    abstract protected function configuration(): array;

    public function contract(): array
    {
        return $this->configuration()['contract'];
    }

    public function analyse(array $csv): array
    {
        $c = $this->configuration();
        $prepared = [];
        $pairs = [];
        $slugs = [];
        foreach ($csv['rows'] as $row) {
            $data = $row['data'];
            foreach ($data as $key => $value) if ($key !== $c['external']) $data[$key] = trim($value);
            if (isset($data[$c['provider']])) $data[$c['provider']] = mb_strtolower($data[$c['provider']]);
            if (isset($data[$c['slug']])) $data[$c['slug']] = str($data[$c['slug']])->slug()->lower()->toString();
            if (isset($data['country_code']) && $data['country_code'] !== '') $data['country_code'] = strtoupper($data['country_code']);
            $pair = ($data[$c['provider']] ?? '') . "\0" . ($data[$c['external']] ?? '');
            if (($data[$c['provider']] ?? '') !== '' || ($data[$c['external']] ?? '') !== '') $pairs[$pair][] = $row['row_number'];
            if (($data[$c['slug']] ?? '') !== '') $slugs[$data[$c['slug']]][] = $row['row_number'];
            $prepared[] = $row + ['normalized' => $data, 'pair' => $pair];
        }
        $modelClass = $c['model'];
        $identityClass = $c['identity_model'];
        $models = $modelClass::whereIn('slug', array_keys($slugs))->get()->keyBy('slug');
        $identities = $identityClass::where(function ($q) use ($pairs) {
            foreach (array_keys($pairs) as $pair) {
                [$p, $e] = explode("\0", $pair, 2);
                $q->orWhere(fn($x) => $x->where('provider', $p)->where('external_id', $e));
            }
        })->get()->keyBy(fn($i) => $i->provider . "\0" . $i->external_id);
        $results = [];
        foreach ($prepared as $row) {
            $d = $row['normalized'];
            $n = $row['row_number'];
            $provider = $d[$c['provider']] ?? '';
            $external = $d[$c['external']] ?? '';
            $slug = $d[$c['slug']] ?? '';
            $identifier = $provider !== '' ? "{$provider} / {$external}" : $slug;
            if (($provider === '') xor ($external === '')) {
                $results[] = $this->rows->error($n, $row['data'], $identifier, 'Provider and external ID must be supplied together.');
                continue;
            }
            if ($provider === '' && $slug === '') {
                $results[] = $this->rows->error($n, $row['data'], $identifier, 'Supply a provider identity or slug. Names are never used for matching.');
                continue;
            }
            if ($provider !== '' && count($pairs[$row['pair']]) > 1) {
                $results[] = $this->rows->error($n, $row['data'], $identifier, 'Duplicate external identity also appears on CSV row ' . implode(', ', array_diff($pairs[$row['pair']], [$n])) . '.');
                continue;
            }
            if ($slug !== '' && count($slugs[$slug]) > 1) {
                $results[] = $this->rows->error($n, $row['data'], $identifier, 'Duplicate slug also appears on CSV row ' . implode(', ', array_diff($slugs[$slug], [$n])) . '.');
                continue;
            }
            $identity = $provider !== '' ? $identities->get($row['pair']) : null;
            $bySlug = $slug !== '' ? $models->get($slug) : null;
            $identityModel = $identity?->{$c['identity_relation']};
            if ($identityModel && $bySlug && $identityModel->id !== $bySlug->id) {
                $results[] = $this->rows->error($n, $row['data'], $identifier, "{$c['slug']} resolves to a different {$c['label']} than the supplied external identity.");
                continue;
            }
            $model = $identityModel ?: $bySlug;
            $rules = [];
            if (! $model) foreach ($c['required_create'] as $field) $rules[$field] = ['required', 'string', 'max:255'];
            foreach ($c['rules'] as $field => $rule) if (($d[$field] ?? '') !== '') $rules[$field] = $rule;
            try {
                $this->rows->validate($d, $rules);
            } catch (ValidationException $e) {
                $results[] = $this->rows->error($n, $row['data'], $identifier, $e->validator->errors()->all());
                continue;
            }
            $payload = array_intersect_key($d, array_flip($c['payload']));
            $payload = array_filter($payload, static fn(string $value): bool => $value !== '');
            if (! $model && in_array('is_active', $c['payload'], true)) $payload += ['is_active' => true];
            if (! array_key_exists('slug', $payload) && $slug !== '' && ! $model) $payload['slug'] = $slug;
            $changes = $model ? $this->rows->changedFields($model, $payload) : array_keys($payload);
            $needsMapping = $provider !== '' && ! $identity;
            if ($needsMapping && $model) $changes[] = 'external_identity';
            $results[] = ['row_number' => $n, 'data' => $row['data'], 'identifier' => $identifier, 'action' => !$model ? 'create' : ($changes ? 'update' : 'unchanged'), 'changes' => $changes, 'warnings' => [], 'errors' => [], 'model_id' => $model?->id, 'payload' => $payload, 'provider' => $provider, 'external_id' => $external, 'needs_mapping' => $needsMapping];
        }
        return $this->rows->summarize($results);
    }

    public function execute(array $analysis): void
    {
        $c = $this->configuration();
        $modelClass = $c['model'];
        $identityClass = $c['identity_model'];
        foreach ($analysis['rows'] as $row) {
            if (! in_array($row['action'], ['create', 'update'], true)) continue;
            $identity = $row['provider'] !== '' ? $identityClass::where('provider', $row['provider'])->where('external_id', $row['external_id'])->first() : null;
            $bySlug = isset($row['payload']['slug']) ? $modelClass::where('slug', $row['payload']['slug'])->first() : null;
            $model = $identity?->{$c['identity_relation']} ?: $bySlug;
            if (($row['model_id'] ?? null) !== $model?->id) throw new \RuntimeException("Identity changed since analysis at CSV row {$row['row_number']}.");
            if ($model) {
                $model->fill($row['payload'])->save();
            } else {
                $model = $modelClass::create($row['payload']);
            }
            if ($row['needs_mapping']) $identityClass::create([$c['foreign_key'] => $model->id, 'provider' => $row['provider'], 'external_id' => $row['external_id']]);
        }
    }
}
