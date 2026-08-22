<?php

namespace App\Services\Import\Importers;

use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;

class RealClubCsvImporter extends ExternalIdentityCsvImporter
{
    protected function configuration(): array
    {
        return [
            'model' => RealClub::class,
            'identity_model' => RealClubExternalIdentity::class,
            'identity_relation' => 'realClub',
            'foreign_key' => 'real_club_id',
            'label' => 'RealClub',
            'provider' => 'club_provider',
            'external' => 'club_external_id',
            'slug' => 'club_slug',
            'payload' => ['name', 'short_name', 'slug', 'country_code', 'logo_path'],
            'required_create' => ['name', 'short_name', 'club_slug'],
            'nullable' => ['country_code', 'logo_path'],
            'rules' => ['name' => ['required', 'string', 'max:255'], 'short_name' => ['required', 'string', 'max:32'], 'club_slug' => ['nullable', 'string', 'max:255'], 'country_code' => ['nullable', 'alpha:ascii', 'size:2'], 'logo_path' => ['nullable', 'string', 'max:255']],
            'contract' => [
                'columns' => ['club_provider', 'club_external_id', 'club_slug', 'name', 'short_name', 'country_code', 'logo_path'],
                'required_header' => [],
                'identifier' => 'Preferred: club_provider + club_external_id; fallback: club_slug',
                'required_create' => ['name', 'short_name', 'club_slug'],
                'optional' => ['club_provider', 'club_external_id', 'country_code', 'logo_path'],
                'formats' => ['provider: trimmed and lowercase', 'external ID: exact case and punctuation', 'country_code: two letters'],
                'behavior' => 'Updates supplied columns only; provider mappings are created atomically.',
                'dependency' => 'Import after real competitions and before seasons.',
                'example' => ['opta', 'Club-001', 'fc-example', 'FC Example', 'FCE', 'DE', ''],
                'caveats' => ['Names are never used for matching. Slug is required on create. External IDs are preserved exactly.'],
            ],
        ];
    }
}
