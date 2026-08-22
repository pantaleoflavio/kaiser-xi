<?php

namespace App\Enums;

enum PlayerScoreStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case DidNotPlay = 'did_not_play';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
