<?php

namespace App\Filament\Resources\Players;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
use App\Models\Player;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlayerResource extends Resource
{
    use ProtectsHistoricalDeletion;

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
            TextInput::make('first_name')->label(__('admin.labels.first_name')),
            TextInput::make('last_name')->label(__('admin.labels.last_name')),
            TextInput::make('display_name')->label(__('admin.labels.display_name'))->required(),
            TextInput::make('slug')->label(__('admin.labels.slug'))->required()->unique(ignoreRecord: true),
            DatePicker::make('birth_date')->label(__('admin.labels.birth_date')),
            Toggle::make('is_active')->label(__('admin.labels.active'))->default(true),
            Repeater::make('externalIdentities')->label(__('admin.labels.external_identities'))->relationship()->schema([
                TextInput::make('provider')
                    ->label(__('admin.labels.external_provider'))
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(
                        fn(?string $state): ?string => $state !== null
                            ? mb_strtolower(trim($state))
                            : null
                    ),
                TextInput::make('external_id')->label(__('admin.labels.external_id'))->required()->maxLength(255),
            ])->columns(2)->defaultItems(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label(__('admin.labels.display_name'))->searchable()->sortable(),
                TextColumn::make('first_name')->label(__('admin.labels.first_name'))->searchable()->sortable(),
                TextColumn::make('last_name')->label(__('admin.labels.last_name'))->searchable()->sortable(),
                TextColumn::make('external_identities_count')->label(__('admin.labels.external_identities'))->counts('externalIdentities')->sortable(),
                TextColumn::make('birth_date')->label(__('admin.labels.birth_date'))->date()->sortable(),
                IconColumn::make('is_active')->label(__('admin.labels.active'))->boolean(),
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
