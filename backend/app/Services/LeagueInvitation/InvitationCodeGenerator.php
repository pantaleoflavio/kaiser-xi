<?php

namespace App\Services\LeagueInvitation;

class InvitationCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 12;

    public function generate(): string
    {
        return collect(range(1, self::LENGTH))
            ->map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->join('');
    }
}
