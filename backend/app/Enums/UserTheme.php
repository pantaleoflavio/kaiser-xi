<?php

namespace App\Enums;

enum UserTheme: string
{
    case ImperialCrimson = 'imperial-crimson';
    case GoldenWall = 'golden-wall';
    case RoyalStandard = 'royal-standard';
    case NorthernFlame = 'northern-flame';
    case WhiteCrown = 'white-crown';
    case CatalanNight = 'catalan-night';
    case RedStripes = 'red-stripes';
    case LondonRed = 'london-red';
    case SkyKingdom = 'sky-kingdom';
    case MerseyRed = 'mersey-red';
    case BlackCrown = 'black-crown';
    case NerazzurroNight = 'nerazzurro-night';
    case Rossonero = 'rossonero';
    case ImperialBurgundy = 'imperial-burgundy';

    public const DEFAULT = self::ImperialCrimson;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
