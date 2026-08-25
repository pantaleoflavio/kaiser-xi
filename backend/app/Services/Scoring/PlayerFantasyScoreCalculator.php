<?php

namespace App\Services\Scoring;

use App\Models\League;
use App\Models\PlayerScore;

class PlayerFantasyScoreCalculator
{
    public function calculateCents(PlayerScore $score, League $league): int
    {
        return $this->breakdown($score, $league)['fantasy_score_cents'];
    }

    /**
     * The canonical player-level calculation. Consumers must use this method (or
     * calculateCents(), which delegates to it) rather than interpreting events.
     *
     * @return array{base_rating_cents:int, components:list<array{type:string,count:int,coefficient_cents:int,total_cents:int}>, fantasy_score_cents:int}
     */
    public function breakdown(PlayerScore $score, League $league): array
    {
        $penaltyGoals = min($score->goals, $score->penalties_scored);
        $openPlayGoals = $score->goals - $penaltyGoals;
        $definitions = [
            ['goal', $openPlayGoals, $league->goalBonus()],
            ['penalty_scored', $penaltyGoals, $league->penaltyScoredBonus()],
            ['assist', $score->assists, $league->assistBonus()],
            ['yellow_card', $score->yellow_cards, $league->yellowCardMalus()],
            ['red_card', $score->red_cards, $league->redCardMalus()],
            ['own_goal', $score->own_goals, $league->ownGoalMalus()],
            ['penalty_missed', $score->penalties_missed, $league->penaltyMissedMalus()],
            ['penalty_saved', $score->penalties_saved, $league->penaltySavedBonus()],
            ['goal_conceded', $score->goals_conceded, $league->goalConcededMalus()],
        ];
        $components = [];
        foreach ($definitions as [$type, $count, $coefficient]) {
            if ($count === 0) continue;
            $coefficientCents = $this->cents($coefficient);
            $components[] = compact('type', 'count') + [
                'coefficient_cents' => $coefficientCents,
                'total_cents' => $count * $coefficientCents,
            ];
        }
        $baseRatingCents = $this->cents($score->base_rating);

        return [
            'base_rating_cents' => $baseRatingCents,
            'components' => $components,
            'fantasy_score_cents' => $baseRatingCents + array_sum(array_column($components, 'total_cents')),
        ];
    }

    private function cents(float|string|null $points): int
    {
        return (int) round((float) $points * 100);
    }
}
