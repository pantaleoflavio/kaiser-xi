<?php

namespace App\Data\Standings;

final readonly class FormulaOnePlacement
{
    public function __construct(
        public int $fantasyTeamId,
        public int $matchdayId,
        public int $position,
        public string $fantasyPoints,
        public int $championshipPoints,
    ) {}
}
