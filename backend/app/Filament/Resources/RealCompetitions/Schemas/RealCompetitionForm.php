<?php

namespace App\Filament\Resources\RealCompetitions\Schemas;

use App\Models\RealCompetition;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RealCompetitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->dehydrateStateUsing(
                        fn(string $state): string => RealCompetition::normalizeCode($state)
                    ),
                TextInput::make('country_code'),
                TextInput::make('type')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
