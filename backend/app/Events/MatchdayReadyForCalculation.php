<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MatchdayReadyForCalculation
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $matchdayId) {}
}
