<?php

namespace App\Filament\Resources\Players;

use App\Models\Player;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.players.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.players.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.players.plural');
    }

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('external_id')->label(__('admin.labels.external_id')),
            TextInput::make('first_name')->label(__('admin.labels.first_name')),
            TextInput::make('last_name')->label(__('admin.labels.last_name')),
            TextInput::make('display_name')->label(__('admin.labels.display_name'))->required(),
            TextInput::make('slug')->label(__('admin.labels.slug'))->required()->unique(ignoreRecord: true),
            DatePicker::make('birth_date')->label(__('admin.labels.birth_date')),
            Toggle::make('is_active')->label(__('admin.labels.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label(__('admin.labels.display_name'))->searchable()->sortable(),
                TextColumn::make('first_name')->label(__('admin.labels.first_name'))->searchable()->sortable(),
                TextColumn::make('last_name')->label(__('admin.labels.last_name'))->searchable()->sortable(),
                TextColumn::make('external_id')->label(__('admin.labels.external_id'))->searchable()->sortable(),
                TextColumn::make('birth_date')->label(__('admin.labels.birth_date'))->dateTime()->sortable(),
            IconColumn::make('is_active')->label(__('admin.labels.is_active'))->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
            'create' => Pages\CreatePlayer::route('/create'),
            'edit' => Pages\EditPlayer::route('/{record}/edit'),
        ];
    }
}
