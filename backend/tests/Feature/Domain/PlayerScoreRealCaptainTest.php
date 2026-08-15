<?php

namespace Tests\Feature\Domain;

use App\Models\PlayerScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerScoreRealCaptainTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_captain_flag_defaults_false_and_casts_to_boolean(): void
    {
        $score = PlayerScore::factory()->create();

        $this->assertFalse($score->is_captain);
        $this->assertIsBool($score->is_captain);
    }

    public function test_captain_state_marks_real_performance_without_changing_score_or_playability(): void
    {
        $score = PlayerScore::factory()->captain()->confirmed(7.0)->create();

        $this->assertTrue($score->is_captain);
        $this->assertSame('7.00', $score->final_score);
        $this->assertTrue($score->isPlayable());
    }
}
