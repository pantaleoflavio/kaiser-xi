<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Matchday;
use App\Models\Player;
use App\Models\PlayerSeasonRegistration;
use App\Models\RealClub;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Eloquent\Model;

trait ProtectsHistoricalDeletion
{
    public static function canDelete(Model $record): bool
    {
        return match (true) {
            $record instanceof PlayerSeasonRegistration => ! $record->playerScores()->exists(),
            $record instanceof Matchday => ! $record->playerScores()->exists(),
            $record instanceof SeasonClub => ! PlayerSeasonRegistration::query()->where('season_club_id', $record->id)->whereHas('playerScores')->exists(),
            $record instanceof Season => ! Matchday::query()->where('season_id', $record->id)->whereHas('playerScores')->exists(),
            $record instanceof Player => ! $record->playerSeasonRegistrations()->whereHas('playerScores')->exists(),
            $record instanceof RealClub => ! SeasonClub::query()->where('real_club_id', $record->id)->whereHas('playerSeasonRegistrations.playerScores')->exists(),
            default => true,
        };
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
