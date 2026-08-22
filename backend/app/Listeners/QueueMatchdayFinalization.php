<?php

namespace App\Listeners;

use App\Events\MatchdayReadyForCalculation;
use App\Jobs\FinalizeMatchdayJob;

final class QueueMatchdayFinalization
{
    public function handle(MatchdayReadyForCalculation $event): void
    {
        FinalizeMatchdayJob::dispatch($event->matchdayId);
    }
}
