<?php

namespace App\Enums;

enum UserTheme: string
{
    case BavarianRed = 'bavarian-red';
    case WestphalianYellow = 'westphalian-yellow';
    case Koenigsblau = 'koenigsblau';
    case RhineRedBlack = 'rhine-red-black';
    case BlancosWhite = 'blancos-white';
    case BlaugranaNight = 'blaugrana-night';
    case Colchonero = 'colchonero';
    case GunnersRed = 'gunners-red';
    case ManchesterSky = 'manchester-sky';
    case MerseyRed = 'mersey-red';
    case VecchiaSignora = 'vecchia-signora';
    case NerazzurroNight = 'nerazzurro-night';
    case MilanoRedBlack = 'milano-red-black';
    case LaLupaRedGold = 'la-lupa-red-gold';

    public const DEFAULT = self::BavarianRed;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
