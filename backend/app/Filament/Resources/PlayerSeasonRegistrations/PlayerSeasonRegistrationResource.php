<?php

namespace App\Filament\Resources\PlayerSeasonRegistrations;

use App\Filament\Resources\Concerns\ProtectsHistoricalDeletion;
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
use App\Models\Player;
use App\Models\RealClub;
use App\Models\RealCompetition;
use App\Models\Season;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

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
            Toggle::make('is_active')->label(__('admin.labels.active'))->default(true),
            DatePicker::make('registered_on')->label(__('admin.labels.registered_on')),
            DatePicker::make('released_on')->label(__('admin.labels.released_on')),
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
            TextColumn::make('registered_on')->label(__('admin.labels.registered_on'))->date()->sortable(),
            TextColumn::make('released_on')->label(__('admin.labels.released_on'))->date()->sortable(),
            IconColumn::make('is_active')->label(__('admin.labels.active'))->boolean(),
        ])->filters([
            SelectFilter::make('season')
                ->label(__('admin.labels.season'))
                ->options(fn(): array => Season::query()->orderByDesc('starts_at')->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn(Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn(Builder $query, int|string $seasonId): Builder => $query->whereHas(
                        'seasonClub',
                        fn(Builder $query): Builder => $query->where('season_id', $seasonId),
                    ),
                )),
            SelectFilter::make('competition')
                ->label(__('admin.labels.competition'))
                ->options(fn(): array => RealCompetition::query()->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn(Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn(Builder $query, int|string $competitionId): Builder => $query->whereHas(
                        'seasonClub.season',
                        fn(Builder $query): Builder => $query->where('real_competition_id', $competitionId),
                    ),
                )),
            SelectFilter::make('registered_club')
                ->label(__('admin.labels.registered_club'))
                ->options(fn(): array => RealClub::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->query(fn(Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn(Builder $query, int|string $clubId): Builder => $query->whereHas(
                        'seasonClub',
                        fn(Builder $query): Builder => $query->where('real_club_id', $clubId),
                    ),
                )),
            SelectFilter::make('player')
                ->label(__('admin.labels.player'))
                ->options(fn(): array => Player::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                ->searchable()
                ->query(fn(Builder $query, array $data): Builder => $query->when(
                    $data['value'] ?? null,
                    fn(Builder $query, int|string $playerId): Builder => $query->where('player_id', $playerId),
                )),
            TernaryFilter::make('is_active')->label(__('admin.labels.active')),
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
