<?php

namespace App\Services\Scoring;

use App\Models\League;
use App\Models\PlayerScore;

class PlayerFantasyScoreCalculator
{
    public function calculateCents(PlayerScore $score, League $league): int
    {
        $penaltyGoals = min($score->goals, $score->penalties_scored);
        $openPlayGoals = $score->goals - $penaltyGoals;

        return $this->cents($score->base_rating)
            + $openPlayGoals * $this->cents($league->goalBonus())
            + $penaltyGoals * $this->cents($league->penaltyScoredBonus())
            + $score->assists * $this->cents($league->assistBonus())
            + $score->yellow_cards * $this->cents($league->yellowCardMalus())
            + $score->red_cards * $this->cents($league->redCardMalus())
            + $score->own_goals * $this->cents($league->ownGoalMalus())
            + $score->penalties_missed * $this->cents($league->penaltyMissedMalus())
            + $score->penalties_saved * $this->cents($league->penaltySavedBonus())
            + $score->goals_conceded * $this->cents($league->goalConcededMalus());
    }

    private function cents(float|string|null $points): int
    {
        return (int) round((float) $points * 100);
    }
}
