<?php

namespace App\Services\Formation;

use App\Exceptions\LeagueScheduleNotInitializedException;
use App\Models\League;
use App\Models\Matchday;

class AssertFormationEligibility
{
    public function assert(League $league, Matchday $matchday): void
    {
        if ($league->isHeadToHead() && ! $league->allowsFormationFor($matchday)) {
            throw new LeagueScheduleNotInitializedException;
        }
    }
}
