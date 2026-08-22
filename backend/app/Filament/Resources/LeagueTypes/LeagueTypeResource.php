<?php

namespace App\Filament\Resources\LeagueTypes;

use App\Models\LeagueType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeagueTypeResource extends Resource
{
    protected static ?string $model = LeagueType::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.lookups');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.league_types.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.league_types.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.league_types.plural');
    }

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->label(__('admin.labels.key'))->required()->unique(ignoreRecord: true),
            TextInput::make('label')->label(__('admin.labels.label'))->required(),
            Textarea::make('description')->label(__('admin.labels.description'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->label(__('admin.labels.key'))->searchable()->sortable(),
                TextColumn::make('label')->label(__('admin.labels.label'))->searchable()->sortable(),
                TextColumn::make('description')->label(__('admin.labels.description'))->searchable()->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeagueTypes::route('/'),
            'create' => Pages\CreateLeagueType::route('/create'),
            'edit' => Pages\EditLeagueType::route('/{record}/edit'),
        ];
    }
}
