<?php

namespace App\Filament\Resources\RealMatches;

use App\Enums\RealMatchStatus;
use App\Filament\Resources\RealMatches\Pages\CreateRealMatch;
use App\Filament\Resources\RealMatches\Pages\EditRealMatch;
use App\Filament\Resources\RealMatches\Pages\ListRealMatches;
use App\Models\RealMatch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RealMatchResource extends Resource
{
    protected static ?string $model = RealMatch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.real_matches.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.real_matches.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.real_matches.plural');
    }

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('matchday_id')->label('Matchday')->relationship('matchday', 'name')->searchable()->preload()->required(),
            Select::make('home_season_club_id')
                ->label('Home club')
                ->relationship('homeSeasonClub', 'id')
                ->getOptionLabelFromRecordUsing(
                    fn($record): string => $record->realClub->name
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('away_season_club_id')
                ->label('Away club')
                ->relationship('awaySeasonClub', 'id')
                ->getOptionLabelFromRecordUsing(
                    fn($record): string => $record->realClub->name
                )
                ->searchable()
                ->preload()
                ->required(),
            DateTimePicker::make('kickoff_at')
                ->label(__('admin.labels.kickoff_at'))
                ->seconds(false)
                ->required(),
            TextInput::make('home_score')->label(__('admin.labels.home_score'))->numeric(),
            TextInput::make('away_score')->label(__('admin.labels.away_score'))->numeric(),
            Select::make('status')->label(__('admin.labels.status'))->options(RealMatchStatus::options())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matchday.season.realCompetition.name')->label(__('admin.labels.competition')),
                TextColumn::make('matchday.season.name')->label(__('admin.labels.season')),
                TextColumn::make('matchday.name')->label(__('admin.labels.matchday'))->searchable()->sortable(),
                TextColumn::make('homeSeasonClub.realClub.name')->label(__('admin.labels.home_club'))->searchable()->sortable(),
                TextColumn::make('awaySeasonClub.realClub.name')->label(__('admin.labels.away_club'))->searchable()->sortable(),
                TextColumn::make('kickoff_at')->label(__('admin.labels.kickoff_at'))->dateTime()->sortable(),
                TextColumn::make('home_score')->label(__('admin.labels.home_score'))->searchable()->sortable(),
                TextColumn::make('away_score')->label(__('admin.labels.away_score'))->searchable()->sortable(),
                TextColumn::make('status')->label(__('admin.labels.status'))->searchable()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRealMatches::route('/'),
            'create' => CreateRealMatch::route('/create'),
            'edit' => EditRealMatch::route('/{record}/edit'),
        ];
    }
}
