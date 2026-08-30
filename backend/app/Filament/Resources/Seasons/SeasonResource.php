<?php

namespace App\Filament\Resources\Seasons;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
use App\Filament\Resources\Seasons\Pages\CreateSeason;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\Pages\ListSeasons;
use App\Models\Season;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeasonResource extends Resource
{
    use ProtectsHistoricalDeletion;

    protected static ?string $model = Season::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.competitions');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.seasons.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.seasons.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.seasons.plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('real_competition_id')->label(__('admin.labels.real_competition'))->relationship('realCompetition', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->label(__('admin.labels.name'))->required(),
            DatePicker::make('starts_at')->label(__('admin.labels.starts_at'))->required(),
            DatePicker::make('ends_at')->label(__('admin.labels.ends_at'))->required(),
            Toggle::make('is_active')->label(__('admin.labels.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('realCompetition.name')->label(__('admin.labels.competition'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('admin.labels.name'))->searchable()->sortable(),
                TextColumn::make('starts_at')->label(__('admin.labels.starts_at'))->date()->sortable(),
                TextColumn::make('ends_at')->label(__('admin.labels.ends_at'))->date()->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasons::route('/'),
            'create' => CreateSeason::route('/create'),
            'edit' => EditSeason::route('/{record}/edit'),
        ];
    }
}
