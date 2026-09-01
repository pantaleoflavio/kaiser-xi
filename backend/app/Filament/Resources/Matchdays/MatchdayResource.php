<?php

namespace App\Filament\Resources\Matchdays;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
use App\Models\Matchday;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\Matchday\UnlockMatchdayCalculation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MatchdayResource extends Resource
{
    use ProtectsHistoricalDeletion;

    protected static ?string $model = Matchday::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.real_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.matchdays.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.matchdays.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.matchdays.plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('season_id')->label(__('admin.labels.season'))->relationship('season', 'name')->searchable()->preload()->required(),
            TextInput::make('number')->label(__('admin.labels.matchday_number'))->numeric()->required(),
            TextInput::make('name')->label(__('admin.labels.round_name')),
            DateTimePicker::make('starts_at')->label(__('admin.labels.starts_at'))->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label(__('admin.labels.ends_at'))->seconds(false)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('season.realCompetition.name')->label(__('admin.labels.competition'))->searchable()->sortable(),
                TextColumn::make('season.name')->label(__('admin.labels.season'))->searchable()->sortable(),
                TextColumn::make('number')->label(__('admin.labels.matchday_number'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('admin.labels.round_name'))->searchable()->sortable(),
                TextColumn::make('starts_at')->label(__('admin.labels.starts_at'))->dateTime()->sortable(),
                TextColumn::make('ends_at')->label(__('admin.labels.ends_at'))->dateTime()->sortable(),
                TextColumn::make('calculation_unlocked_at')->label(__('admin.resources.matchdays.calculation_unlock'))->dateTime()->placeholder(__('admin.resources.matchdays.locked')),
            ])
            ->recordActions([
                Action::make('unlockCalculation')
                    ->label(__('admin.resources.matchdays.unlock_calculation'))
                    ->icon('heroicon-o-lock-open')
                    ->requiresConfirmation()
                    ->visible(fn(Matchday $record): bool => auth()->user()?->canAccessAdminPanel() === true && $record->calculation_unlocked_at === null && now()->gte($record->ends_at))
                    ->authorize(fn(): bool => auth()->user()?->canAccessAdminPanel() === true)
                    ->action(function (Matchday $record): void {
                        app(UnlockMatchdayCalculation::class)->unlock($record, auth()->user());
                        Notification::make()->success()->title(__('admin.resources.matchdays.unlocked'))->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatchdays::route('/'),
            'create' => Pages\CreateMatchday::route('/create'),
            'edit' => Pages\EditMatchday::route('/{record}/edit'),
        ];
    }
}
