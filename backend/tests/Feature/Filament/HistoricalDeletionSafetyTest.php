<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Matchdays\MatchdayResource;
use App\Filament\Resources\PlayerSeasonRegistrations\PlayerSeasonRegistrationResource;
use App\Models\PlayerScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_matchday_with_score_history_cannot_be_deleted_in_filament(): void
    {
        $score = PlayerScore::factory()->create();

        $this->assertFalse(PlayerSeasonRegistrationResource::canDelete($score->playerSeasonRegistration));
        $this->assertFalse(MatchdayResource::canDelete($score->matchday));
        $this->assertFalse(PlayerSeasonRegistrationResource::canDeleteAny());
        $this->assertFalse(MatchdayResource::canDeleteAny());
    }
}
