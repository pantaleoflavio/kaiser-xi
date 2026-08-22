<?php

namespace App\Services\Scoring;

use InvalidArgumentException;

final class ConvertTeamPointsToGoals
{
    public function convert(float $points, float $firstGoalThreshold, float $goalInterval): int
    {
        if ($goalInterval <= 0) {
            throw new InvalidArgumentException('The goal interval must be greater than zero.');
        }

        $pointsUnits = (int) round($points * 100);
        $thresholdUnits = (int) round($firstGoalThreshold * 100);
        $intervalUnits = (int) round($goalInterval * 100);

        if ($pointsUnits < $thresholdUnits) {
            return 0;
        }

        return 1 + intdiv($pointsUnits - $thresholdUnits, $intervalUnits);
    }
}
