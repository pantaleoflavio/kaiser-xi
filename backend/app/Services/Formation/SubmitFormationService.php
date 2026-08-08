<?php

namespace App\Services\Formation;

use App\Models\Formation;
use App\Models\Matchday;

class SubmitFormationService
{
    public function __construct(private SaveFormationService $saveFormationService) {}

    public function submit(Formation $formation, Matchday $matchday): Formation
    {
        $this->saveFormationService->assertBeforeDeadline($matchday);
        $formation->update(['submitted_at' => now(), 'is_confirmed' => true]);

        return $formation->load($this->saveFormationService->relations());
    }
}