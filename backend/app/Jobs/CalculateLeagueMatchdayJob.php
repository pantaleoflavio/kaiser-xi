<?php

namespace App\Jobs;

use App\Services\Matchday\LeagueMatchdayCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CalculateLeagueMatchdayJob implements ShouldQueue
{
    use Queueable;

    public int $tries;
    public int $timeout;

    public function __construct(public readonly int $leagueId, public readonly int $matchdayId, public readonly string $executionToken)
    {
        $this->tries = (int) config('queue.calculations.tries');
        $this->timeout = (int) config('queue.calculations.timeout');
    }

    public function backoff(): int
    {
        return (int) config('queue.calculations.backoff');
    }

    public function handle(LeagueMatchdayCalculationService $service): void
    {
        $service->execute($this->leagueId, $this->matchdayId, $this->executionToken);
    }

    public function failed(?Throwable $exception): void
    {
        app(LeagueMatchdayCalculationService::class)->fail($this->leagueId, $this->matchdayId, $this->executionToken, $exception);
    }
}
