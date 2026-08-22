<?php

namespace App\Filament\Resources\PlayerSeasonRegistrations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlayerSeasonRegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('player_id')
                    ->relationship('player', 'id')
                    ->required(),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Select::make('real_club_id')
                    ->relationship('realClub', 'name')
                    ->required(),
                TextInput::make('external_provider')->maxLength(255)->requiredWith('external_id')->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null),
                TextInput::make('external_id')->maxLength(255)->requiredWith('external_provider'),
                TextInput::make('shirt_number')
                    ->numeric(),
                TextInput::make('quotation')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('registered_at'),
                DateTimePicker::make('released_at'),
            ]);
    }
}
