<?php

namespace App\Services\Import\Importers;

use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use Illuminate\Validation\Rule;

class PlayerCsvImporter extends ExternalIdentityCsvImporter
{
    protected function configuration(): array
    {
        return [
            'model' => Player::class,
            'identity_model' => PlayerExternalIdentity::class,
            'identity_relation' => 'player',
            'foreign_key' => 'player_id',
            'label' => 'Player',
            'provider' => 'player_provider',
            'external' => 'player_external_id',
            'slug' => 'player_slug',
            'payload' => ['first_name', 'last_name', 'display_name', 'slug', 'birth_date', 'is_active'],
            'required_create' => ['display_name', 'player_slug'],
            'nullable' => ['first_name', 'last_name', 'birth_date'],
            'rules' => ['first_name' => ['nullable', 'string', 'max:255'], 'last_name' => ['nullable', 'string', 'max:255'], 'display_name' => ['required', 'string', 'max:255'], 'player_slug' => ['nullable', 'string', 'max:255'], 'birth_date' => ['nullable', 'date_format:Y-m-d'], 'is_active' => ['required', Rule::in(['true', 'false'])]],
            'contract' => [
                'columns' => ['player_provider', 'player_external_id', 'player_slug', 'first_name', 'last_name', 'display_name', 'birth_date', 'is_active'],
                'required_header' => [],
                'identifier' => 'Preferred: player_provider + player_external_id; fallback: player_slug',
                'required_create' => ['display_name', 'player_slug'],
                'optional' => ['player_provider', 'player_external_id', 'first_name', 'last_name', 'birth_date', 'is_active'],
                'formats' => ['provider: trimmed and lowercase', 'external ID: exact case and punctuation', 'birth_date: YYYY-MM-DD', 'is_active: true or false'],
                'behavior' => 'Updates supplied columns only; is_active defaults true on create.',
                'dependency' => 'Import after real clubs and before player registrations.',
                'example' => ['opta', 'Player-001', 'jane-doe', 'Jane', 'Doe', 'Jane Doe', '2000-01-02', 'true'],
                'caveats' => ['Names are never used for matching. Player.external_id no longer exists. Provider identity is preferred for provider data.'],
            ],
        ];
    }
}
