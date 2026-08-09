<?php

namespace Tests\Unit\Services\League;

use App\Services\League\HeadToHeadRoundRobinGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HeadToHeadRoundRobinGeneratorTest extends TestCase
{
    #[DataProvider('evenTeamCounts')]
    public function test_even_first_legs_are_complete_and_each_team_plays_once_per_round(int $count): void
    {
        $teams = range(1, $count);
        $rounds = (new HeadToHeadRoundRobinGenerator)->firstLeg($teams);

        $this->assertCount($count - 1, $rounds);
        $this->assertSame(array_fill(0, $count - 1, intdiv($count, 2)), array_map('count', $rounds));
        $this->assertRoundAndPairCoverage($teams, $rounds);
    }

    public static function evenTeamCounts(): array
    {
        return [[2], [4], [6]];
    }

    public function test_odd_first_leg_rotates_one_bye_and_covers_every_pair(): void
    {
        $teams = [11, 22, 33, 44, 55];
        $rounds = (new HeadToHeadRoundRobinGenerator)->firstLeg($teams);

        $this->assertCount(5, $rounds);
        $this->assertSame([2, 2, 2, 2, 2], array_map('count', $rounds));
        $this->assertRoundAndPairCoverage($teams, $rounds, false);
        $byeCounts = array_fill_keys($teams, 0);
        foreach ($rounds as $round) {
            $playing = array_merge(array_column($round, 'home'), array_column($round, 'away'));
            $byeCounts[array_values(array_diff($teams, $playing))[0]]++;
        }
        $this->assertSame(array_fill_keys($teams, 1), $byeCounts);
    }

    public function test_return_leg_reverses_every_fixture_and_generation_is_deterministic(): void
    {
        $generator = new HeadToHeadRoundRobinGenerator;
        $teams = [8, 19, 27, 41];
        $schedule = $generator->doubleRoundRobin($teams);
        $firstLeg = array_slice($schedule, 0, 3);
        $returnLeg = array_slice($schedule, 3);

        foreach ($firstLeg as $round => $pairs) {
            $this->assertSame(
                array_map(fn(array $pair): array => ['home' => $pair['away'], 'away' => $pair['home']], $pairs),
                $returnLeg[$round],
            );
        }
        $this->assertSame($schedule, $generator->doubleRoundRobin($teams));
    }

    private function assertRoundAndPairCoverage(array $teams, array $rounds, bool $allPlay = true): void
    {
        $pairs = [];
        foreach ($rounds as $round) {
            $playing = array_merge(array_column($round, 'home'), array_column($round, 'away'));
            $this->assertCount(count($playing), array_unique($playing));
            if ($allPlay) {
                $this->assertEqualsCanonicalizing($teams, $playing);
            }
            foreach ($round as $pair) {
                $ordered = [$pair['home'], $pair['away']];
                sort($ordered);
                $pairs[implode(':', $ordered)] = ($pairs[implode(':', $ordered)] ?? 0) + 1;
            }
        }
        $this->assertCount((count($teams) * (count($teams) - 1)) / 2, $pairs);
        $this->assertSame([1], array_values(array_unique($pairs)));
    }
}
