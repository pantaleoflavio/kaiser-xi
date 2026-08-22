<?php

namespace App\Enums;

enum CsvImportType: string
{
    case RealCompetitions = 'real_competitions';
    case RealClubs = 'real_clubs';
    case Players = 'players';
    case Seasons = 'seasons';
    case SeasonClubs = 'season_clubs';
    case Matchdays = 'matchdays';
    case PlayerSeasonRegistrations = 'player_season_registrations';

    public function label(): string
    {
        return match ($this) {
            self::RealCompetitions => 'Real competitions',
            self::RealClubs => 'Real clubs',
            self::Players => 'Players',
            self::Seasons => 'Seasons',
            self::SeasonClubs => 'Season clubs',
            self::Matchdays => 'Matchdays',
            self::PlayerSeasonRegistrations => 'Player season registrations',
        };
    }
}
