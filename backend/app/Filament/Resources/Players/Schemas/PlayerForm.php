<?php

namespace App\Filament\Resources\Players\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('display_name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                DatePicker::make('birth_date'),
                Toggle::make('is_active')
                    ->required(),
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
