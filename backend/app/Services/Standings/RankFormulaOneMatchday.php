<?php

namespace App\Services\Standings;

use App\Data\Standings\FormulaOnePlacement;
use Illuminate\Support\Collection;

final class RankFormulaOneMatchday
{
    /**
     * @param  Collection<int, int>  $fantasyTeamIds
     * @param  Collection<int, string|int|float>  $scoresByFantasyTeam
     * @param  array<int, int>  $positionPoints
     * @return Collection<int, FormulaOnePlacement>
     */
    public function rank(
        Collection $fantasyTeamIds,
        int $matchdayId,
        Collection $scoresByFantasyTeam,
        array $positionPoints,
    ): Collection {
        return $fantasyTeamIds
            ->map(fn(int $teamId): array => [
                'id' => $teamId,
                'points' => (string) ($scoresByFantasyTeam->get($teamId) ?? '0.00'),
            ])
            ->sort(fn(array $a, array $b): int => bccomp($b['points'], $a['points'], 2)
                ?: $a['id'] <=> $b['id'])
            ->values()
            ->map(fn(array $row, int $index): FormulaOnePlacement => new FormulaOnePlacement(
                fantasyTeamId: $row['id'],
                matchdayId: $matchdayId,
                position: $index + 1,
                fantasyPoints: $row['points'],
                championshipPoints: $positionPoints[$index + 1] ?? 0,
            ));
    }
}
