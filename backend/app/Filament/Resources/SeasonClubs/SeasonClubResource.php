<?php

namespace App\Filament\Resources\SeasonClubs;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
use App\Filament\Resources\SeasonClubs\Pages\CreateSeasonClub;
use App\Filament\Resources\SeasonClubs\Pages\EditSeasonClub;
use App\Filament\Resources\SeasonClubs\Pages\ListSeasonClubs;
use App\Models\SeasonClub;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeasonClubResource extends Resource
{
    use ProtectsHistoricalDeletion;

    protected static ?string $model = SeasonClub::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.season_clubs.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.season_clubs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.season_clubs.plural');
    }

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('season_id')->label(__('admin.labels.season'))->relationship('season', 'name')->searchable()->preload()->required(),
            Select::make('real_club_id')->label(__('admin.labels.real_club'))->relationship('realClub', 'name')->searchable()->preload()->required(),
            TextInput::make('display_name')->label(__('admin.labels.display_name')),
            TextInput::make('external_provider')->label(__('admin.labels.external_provider'))->maxLength(255)->requiredWith('external_id')->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null),
            TextInput::make('external_id')->label(__('admin.labels.external_id'))->maxLength(255)->requiredWith('external_provider'),
            Toggle::make('is_active')->label(__('admin.labels.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('season.realCompetition.name')->label(__('admin.labels.competition'))->searchable()->sortable(),
                TextColumn::make('season.name')->label('Season')->searchable()->sortable(),
                TextColumn::make('realClub.name')->label('Real club')->searchable()->sortable(),
                TextColumn::make('display_name')->label('Display Name')->searchable()->sortable(),
                TextColumn::make('external_id')->label('External Id')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasonClubs::route('/'),
            'create' => CreateSeasonClub::route('/create'),
            'edit' => EditSeasonClub::route('/{record}/edit'),
        ];
    }
}
