<?php

namespace Tests\Unit\Services\Standings;

use App\Services\Standings\RankFormulaOneMatchday;
use PHPUnit\Framework\TestCase;

class RankFormulaOneMatchdayTest extends TestCase
{
    public function test_it_ranks_every_participant_by_score_then_team_id_and_awards_configured_points(): void
    {
        $placements = (new RankFormulaOneMatchday)->rank(
            collect([41, 12, 30, 22]),
            7,
            collect([41 => '78.50', 12 => '72.00', 30 => '72.00']),
            [1 => 10, 2 => 6, 3 => 3],
        );

        self::assertSame([41, 12, 30, 22], $placements->pluck('fantasyTeamId')->all());
        self::assertSame([1, 2, 3, 4], $placements->pluck('position')->all());
        self::assertSame(['78.50', '72.00', '72.00', '0.00'], $placements->pluck('fantasyPoints')->all());
        self::assertSame([10, 6, 3, 0], $placements->pluck('championshipPoints')->all());
    }
}
