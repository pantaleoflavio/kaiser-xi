<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.roles.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.roles.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.roles.plural');
    }

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('admin.labels.name'))->required()->unique(ignoreRecord: true),
            TextInput::make('label')->label(__('admin.labels.label'))->required(),
            TextInput::make('level')->label(__('admin.labels.level'))->numeric(),
            Toggle::make('is_system')->label(__('admin.labels.system_role'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.labels.name'))->searchable()->sortable(),
                TextColumn::make('label')->label(__('admin.labels.label'))->searchable()->sortable(),
                TextColumn::make('level')->label(__('admin.labels.level'))->searchable()->sortable(),
                IconColumn::make('is_system')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
