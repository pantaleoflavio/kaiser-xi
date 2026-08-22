<?php

namespace App\Filament\Resources\PlayerSeasonRegistrations;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlayerSeasonRegistrationResource extends Resource
{
    use ProtectsHistoricalDeletion;

    protected static ?string $modelLabel = 'Player Registration';

    protected static ?string $pluralModelLabel = 'Player Registrations';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.player_season_registrations.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.player_season_registrations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.player_season_registrations.plural');
    }

    protected static ?string $recordTitleAttribute = 'player.display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('player_id')->label(__('admin.labels.player'))->relationship('player', 'display_name')->searchable()->preload()->required(),
            Select::make('season_club_id')->label(__('admin.labels.registered_club'))->relationship('seasonClub', 'id')->getOptionLabelFromRecordUsing(fn($record): string => "{$record->season->realCompetition->name} — {$record->season->name} — {$record->realClub->name}")->searchable()->preload()->required(),
            Select::make('player_role_id')->label(__('admin.labels.player_role'))->relationship('playerRole', 'label')->searchable()->preload()->required(),
            TextInput::make('external_provider')->label(__('admin.labels.external_provider'))->maxLength(255)->requiredWith('external_id')->dehydrateStateUsing(fn(?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null),
            TextInput::make('external_id')->label(__('admin.labels.external_id'))->maxLength(255)->requiredWith('external_provider'),
            TextInput::make('shirt_number')->label(__('admin.labels.shirt_number'))->numeric(),
            TextInput::make('quotation')->label(__('admin.labels.quotation'))->numeric(),
            Toggle::make('is_active')->label(__('admin.labels.is_active'))->default(true),
            DateTimePicker::make('registered_at')->label(__('admin.labels.registered_at')),
            DateTimePicker::make('released_at')->label(__('admin.labels.released_at')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('player.display_name')->label(__('admin.labels.player'))->searchable()->sortable(),
            TextColumn::make('seasonClub.season.realCompetition.name')->label(__('admin.labels.competition')),
            TextColumn::make('seasonClub.season.name')->label(__('admin.labels.season')),
            TextColumn::make('seasonClub.realClub.name')->label(__('admin.labels.registered_club')),
            TextColumn::make('playerRole.label')->label(__('admin.labels.player_role')),
            TextColumn::make('quotation')->label(__('admin.labels.quotation'))->numeric(2),
            IconColumn::make('is_active')->label(__('admin.labels.is_active'))->boolean(),
        ])->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerSeasonRegistrations::route('/'),
            'create' => Pages\CreatePlayerSeasonRegistration::route('/create'),
            'edit' => Pages\EditPlayerSeasonRegistration::route('/{record}/edit'),
        ];
    }
}
