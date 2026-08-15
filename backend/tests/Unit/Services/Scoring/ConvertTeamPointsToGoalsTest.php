<?php

namespace Tests\Unit\Services\Scoring;

use App\Services\Scoring\ConvertTeamPointsToGoals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConvertTeamPointsToGoalsTest extends TestCase
{
    #[DataProvider('defaultThresholdCases')]
    public function test_default_threshold_boundaries(float $points, int $goals): void
    {
        $this->assertSame($goals, (new ConvertTeamPointsToGoals)->convert($points, 66, 6));
    }

    public static function defaultThresholdCases(): array
    {
        return [[65.5, 0], [66, 1], [71.5, 1], [72, 2], [77.5, 2], [78, 3], [84, 4]];
    }

    #[DataProvider('customThresholdCases')]
    public function test_configurable_threshold_boundaries(float $points, int $goals): void
    {
        $this->assertSame($goals, (new ConvertTeamPointsToGoals)->convert($points, 70, 5));
    }

    public static function customThresholdCases(): array
    {
        return [[69.5, 0], [70, 1], [74.5, 1], [75, 2], [80, 3]];
    }
}
