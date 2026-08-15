<?php

namespace App\Jobs;

use App\Models\Matchday;
use App\Services\Matchday\FinalizeMatchday;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FinalizeMatchdayJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $matchdayId) {}

    public function handle(FinalizeMatchday $finalizer): void
    {
        $finalizer->finalize(Matchday::query()->findOrFail($this->matchdayId));
    }
}
