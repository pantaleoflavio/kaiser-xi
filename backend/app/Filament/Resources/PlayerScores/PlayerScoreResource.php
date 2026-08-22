<?php

namespace App\Filament\Resources\PlayerScores;

use App\Filament\Resources\PlayerScores\Schemas\PlayerScoreForm;
use App\Filament\Resources\PlayerScores\Tables\PlayerScoresTable;
use App\Models\PlayerScore;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Table;

class PlayerScoreResource extends Resource
{
    protected static ?string $model = PlayerScore::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.scores');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.player_scores.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.player_scores.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.player_scores.plural');
    }

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return PlayerScoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlayerScoresTable::configure($table);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->isSuperAdmin() || $user->isGlobalAdmin());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerScores::route('/'),
            'create' => Pages\CreatePlayerScore::route('/create'),
            'edit' => Pages\EditPlayerScore::route('/{record}/edit'),
        ];
    }
}
