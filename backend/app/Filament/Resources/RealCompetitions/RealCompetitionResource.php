<?php

namespace App\Filament\Resources\RealCompetitions;

use App\Enums\CompetitionType;
use App\Filament\Resources\RealCompetitions\Pages\CreateRealCompetition;
use App\Filament\Resources\RealCompetitions\Pages\EditRealCompetition;
use App\Filament\Resources\RealCompetitions\Pages\ListRealCompetitions;
use App\Models\RealCompetition;
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

class RealCompetitionResource extends Resource
{
    protected static ?string $model = RealCompetition::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.competitions');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.real_competitions.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.real_competitions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.real_competitions.plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.labels.competition_name'))
                ->required(),
            TextInput::make('code')
                ->label(__('admin.labels.competition_code'))
                ->required()
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(
                    fn(string $state): string => RealCompetition::normalizeCode($state)
                ),
            TextInput::make('country_code')
                ->label(__('admin.labels.country_code'))
                ->length(2)->alpha()
                ->dehydrateStateUsing(
                    fn(?string $state): ?string => $state ? strtoupper($state) : null
                ),
            Select::make('type')
                ->label(__('admin.labels.competition_type'))
                ->options(CompetitionType::options())
                ->required(),
            Toggle::make('is_active')
                ->label(__('admin.labels.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.labels.name'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('admin.labels.competition_code'))->searchable()->sortable(),
                TextColumn::make('country_code')->label(__('admin.labels.country_code'))->searchable()->sortable(),
                TextColumn::make('type')->label(__('admin.labels.competition_type'))->formatStateUsing(fn(CompetitionType $state): string => $state->label())->searchable()->sortable(),
                IconColumn::make('is_active')->label(__('admin.labels.active'))->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRealCompetitions::route('/'),
            'create' => CreateRealCompetition::route('/create'),
            'edit' => EditRealCompetition::route('/{record}/edit'),
        ];
    }
}
