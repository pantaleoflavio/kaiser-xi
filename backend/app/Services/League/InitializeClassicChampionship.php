<?php

namespace App\Services\League;

use App\Models\League;

final class InitializeClassicChampionship
{
    public function __construct(private readonly InitializeChampionship $championship) {}

    public function handle(League $league, int $startMatchdayId): League
    {
        return $this->championship->handle($league, $startMatchdayId);
    }
}
