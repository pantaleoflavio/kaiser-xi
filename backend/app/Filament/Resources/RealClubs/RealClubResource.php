<?php

namespace App\Filament\Resources\RealClubs;

use App\Filament\Resources\RealClubs\Pages\CreateRealClub;
use App\Filament\Resources\RealClubs\Pages\EditRealClub;
use App\Filament\Resources\RealClubs\Pages\ListRealClubs;
use App\Models\RealClub;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RealClubResource extends Resource
{
    protected static ?string $model = RealClub::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.real_clubs.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.real_clubs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.real_clubs.plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Club Name')->required(),
            TextInput::make('short_name')->label(__('admin.labels.short_name'))->required(),
            TextInput::make('slug')->label(__('admin.labels.slug'))->required()->unique(ignoreRecord: true)->dehydrateStateUsing(fn(string $state): string => str($state)->slug()->lower()->toString()),
            TextInput::make('country_code')->label(__('admin.labels.country_code'))->length(2)->alpha()->dehydrateStateUsing(fn(?string $state): ?string => $state ? strtoupper($state) : null),
            TextInput::make('logo_path')->label(__('admin.labels.logo_path')),
            Repeater::make('externalIdentities')->label(__('admin.labels.external_identities'))->relationship()->schema([
                TextInput::make('provider')->label(__('admin.labels.external_provider'))->required()->maxLength(255)->dehydrateStateUsing(fn(string $state): string => mb_strtolower(trim($state))),
                TextInput::make('external_id')->label(__('admin.labels.external_id'))->required()->maxLength(255),
            ])->columns(2)->defaultItems(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.labels.name'))->searchable()->sortable(),
                TextColumn::make('short_name')->label(__('admin.labels.short_name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.labels.slug'))->searchable()->sortable(),
                TextColumn::make('country_code')->label(__('admin.labels.country_code'))->searchable()->sortable(),
                TextColumn::make('external_identities_count')->label(__('admin.labels.external_identities'))->counts('externalIdentities')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRealClubs::route('/'),
            'create' => CreateRealClub::route('/create'),
            'edit' => EditRealClub::route('/{record}/edit'),
        ];
    }
}
