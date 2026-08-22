<?php

namespace Tests\Feature\Services\scoring;

use App\Models\League;
use App\Models\LeagueSetting;
use App\Models\PlayerScore;
use App\Services\Scoring\PlayerFantasyScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerFantasyScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_raw_events_with_two_decimal_arithmetic_without_using_final_score(): void
    {
        $league = League::factory()->create();
        $score = new PlayerScore([
            'base_rating' => 6.25,
            'goals' => 2,
            'assists' => 1,
            'yellow_cards' => 1,
            'red_cards' => 1,
            'own_goals' => 1,
            'penalties_scored' => 1,
            'penalties_missed' => 1,
            'penalties_saved' => 1,
            'goals_conceded' => 2,
            'final_score' => -99,
        ]);

        $this->assertSame(775, app(PlayerFantasyScoreCalculator::class)->calculateCents($score, $league));
        $score->final_score = 99;
        $this->assertSame(775, app(PlayerFantasyScoreCalculator::class)->calculateCents($score, $league));
    }

    public function test_one_global_score_is_scored_differently_by_two_leagues(): void
    {
        $first = League::factory()->create();
        $second = League::factory()->create();
        $second->settings()->create([
            'key' => LeagueSetting::GOAL_BONUS,
            'value' => LeagueSetting::decimalPayload(4.25),
        ]);
        $score = new PlayerScore(['base_rating' => 6, 'goals' => 2]);
        $calculator = app(PlayerFantasyScoreCalculator::class);

        $this->assertSame(1200, $calculator->calculateCents($score, $first));
        $this->assertSame(1450, $calculator->calculateCents($score, $second));
    }

    public function test_penalty_bonus_replaces_the_goal_bonus_and_decimal_negative_rules_are_supported(): void
    {
        $league = League::factory()->create();
        foreach ([LeagueSetting::PENALTY_SCORED_BONUS => 2.75, LeagueSetting::GOAL_CONCEDED_MALUS => -1.25] as $key => $value) {
            $league->settings()->create(['key' => $key, 'value' => LeagueSetting::decimalPayload($value)]);
        }
        $score = new PlayerScore(['base_rating' => 0, 'goals' => 1, 'penalties_scored' => 1, 'goals_conceded' => 3]);

        $this->assertSame(-100, app(PlayerFantasyScoreCalculator::class)->calculateCents($score, $league));
    }
}
