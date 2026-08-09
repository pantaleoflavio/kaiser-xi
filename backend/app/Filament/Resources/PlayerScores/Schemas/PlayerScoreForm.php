<?php

namespace App\Filament\Resources\PlayerScores\Schemas;

use App\Enums\PlayerScoreStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlayerScoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('player_season_registration_id')
                    ->relationship('playerSeasonRegistration', 'id')
                    ->required(),
                Select::make('matchday_id')
                    ->relationship('matchday', 'name')
                    ->required(),
                TextInput::make('base_rating')
                    ->numeric(),
                TextInput::make('goals')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('assists')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('yellow_cards')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('red_cards')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('own_goals')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('penalties_scored')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('penalties_missed')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('penalties_saved')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('goals_conceded')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('clean_sheet')
                    ->required(),
                TextInput::make('final_score')
                    ->numeric(),
                Select::make('status')
                    ->options(PlayerScoreStatus::options())
                    ->required()
                    ->default(PlayerScoreStatus::Pending->value),
            ]);
    }
}
