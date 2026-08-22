<?php

namespace App\Filament\Resources\PlayerScores\Tables;

use App\Enums\PlayerScoreStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlayerScoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matchday.number')
                    ->label(__('admin.labels.matchday'))
                    ->formatStateUsing(fn($record): string => $record->matchday->displayLabel())
                    ->searchable(query: fn(Builder $query, string $search): Builder => $query->whereHas(
                        'matchday',
                        fn(Builder $query): Builder => $query
                            ->where('name', 'like', "%{$search}%")
                            ->when(
                                ctype_digit($search),
                                fn(Builder $query): Builder => $query->orWhere('number', (int) $search),
                            ),
                    ))
                    ->sortable(),
                TextColumn::make('playerSeasonRegistration.player.display_name')->label(__('admin.labels.display_name'))->searchable()->sortable(),
                TextColumn::make('playerSeasonRegistration.seasonClub.realClub.name')->label(__('admin.labels.real_club'))->searchable()->sortable(),
                TextColumn::make('playerSeasonRegistration.playerRole.label')->label(__('admin.labels.player_role'))->sortable(),
                TextColumn::make('base_rating')->label(__('admin.labels.base_rating'))->numeric(2)->sortable(),
                TextColumn::make('final_score')->label(__('admin.labels.final_score'))->numeric(2)->sortable(),
                TextColumn::make('status')->label(__('admin.labels.status'))->formatStateUsing(fn(PlayerScoreStatus $state): string => $state->label())->badge()->sortable(),
                IconColumn::make('is_captain')->label(__('admin.player_scores.captain'))->boolean(),
                IconColumn::make('clean_sheet')->label(__('admin.labels.clean_sheet'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('season')->relationship('matchday.season', 'name')->searchable(),
                SelectFilter::make('matchday')->relationship('matchday', 'name')->searchable(),
                SelectFilter::make('status')->options(PlayerScoreStatus::options()),
                SelectFilter::make('player_role')->relationship('playerSeasonRegistration.playerRole', 'label')->searchable(),
                TernaryFilter::make('missing_final_score')
                    ->label(__('admin.player_scores.missing_final_score'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->whereNull('final_score'),
                        false: fn(Builder $query): Builder => $query->whereNotNull('final_score'),
                    ),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription(__('admin.player_scores.bulk_delete_warning')),
                ]),
            ]);
    }
}
