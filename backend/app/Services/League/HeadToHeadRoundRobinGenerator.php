<?php

namespace App\Services\League;

use InvalidArgumentException;

class HeadToHeadRoundRobinGenerator
{
    /**
     * @param  list<int>  $teamIds
     * @return list<list<array{home: int, away: int}>>
     */
    public function firstLeg(array $teamIds): array
    {
        if (count($teamIds) < 2) {
            throw new InvalidArgumentException('At least two teams are required.');
        }

        if (count($teamIds) !== count(array_unique($teamIds))) {
            throw new InvalidArgumentException('Team identifiers must be unique.');
        }

        /** @var list<int|null> $rotation */
        $rotation = count($teamIds) % 2 === 0 ? $teamIds : [...$teamIds, null];
        $rounds = [];

        for ($round = 0; $round < count($rotation) - 1; $round++) {
            $pairs = [];
            $lastIndex = count($rotation) - 1;

            for ($position = 0; $position < count($rotation) / 2; $position++) {
                $home = $rotation[$position];
                $away = $rotation[$lastIndex - $position];

                if ($home !== null && $away !== null) {
                    $pairs[] = ['home' => $home, 'away' => $away];
                }
            }

            $rounds[] = $pairs;
            $last = array_pop($rotation);
            array_splice($rotation, 1, 0, [$last]);
        }

        return $rounds;
    }

    /**
     * @param  list<int>  $teamIds
     * @return list<list<array{home: int, away: int}>>
     */
    public function doubleRoundRobin(array $teamIds): array
    {
        $firstLeg = $this->firstLeg($teamIds);
        $returnLeg = array_map(
            static fn(array $round): array => array_map(
                static fn(array $pair): array => ['home' => $pair['away'], 'away' => $pair['home']],
                $round,
            ),
            $firstLeg,
        );

        return [...$firstLeg, ...$returnLeg];
    }
}
