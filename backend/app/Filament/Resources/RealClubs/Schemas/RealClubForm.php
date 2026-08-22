<?php

namespace App\Filament\Resources\RealClubs\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RealClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('short_name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('country_code'),
                TextInput::make('logo_path'),
                Repeater::make('externalIdentities')
                    ->relationship()
                    ->schema([
                        TextInput::make('provider')->required()->maxLength(255)->dehydrateStateUsing(fn(string $state): string => mb_strtolower(trim($state))),
                        TextInput::make('external_id')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0),
            ]);
    }
}
