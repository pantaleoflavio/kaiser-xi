<?php

namespace App\Services\Formation;

use App\Exceptions\FormationMatchdayNotEligibleException;
use App\Exceptions\LeagueScheduleNotInitializedException;
use App\Models\League;
use App\Models\Matchday;

class AssertFormationEligibility
{
    public function assert(League $league, Matchday $matchday): void
    {
        $this->assertScheduleContains($league, $matchday);

        if (! $league->isCurrentFormationMatchday($matchday)) {
            throw new FormationMatchdayNotEligibleException;
        }
    }

    public function assertScheduleContains(League $league, Matchday $matchday): void
    {
        $initialized = $league->isHeadToHead()
            ? $league->hasInitializedHeadToHeadSchedule()
            : ! $league->isNonHeadToHeadChampionship() || $league->hasInitializedChampionship();

        if (! $initialized || ! $league->formationScheduleContains($matchday)) {
            throw new LeagueScheduleNotInitializedException;
        }
    }
}
