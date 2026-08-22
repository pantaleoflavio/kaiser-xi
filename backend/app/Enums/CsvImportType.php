<?php

namespace App\Enums;

enum CsvImportType: string
{
    case RealCompetitions = 'real_competitions';
    case RealClubs = 'real_clubs';
    case Players = 'players';

    public function label(): string
    {
        return match ($this) {
            self::RealCompetitions => 'Real competitions',
            self::RealClubs => 'Real clubs',
            self::Players => 'Players',
        };
    }
}
