<?php

namespace Tests\Feature\Domain;

use App\Enums\PlayerScoreStatus;
use App\Models\PlayerScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ValueError;

class PlayerScorePlayabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('scoreStates')]
    public function test_playability_is_defined_by_confirmed_status_and_base_rating(
        PlayerScoreStatus $status,
        ?float $baseRating,
        bool $playable,
    ): void {
        $score = PlayerScore::factory()->create([
            'status' => $status,
            'base_rating' => $baseRating,
        ]);

        $this->assertSame($playable, $score->isPlayable());
    }

    /** @return iterable<string, array{PlayerScoreStatus, ?float, bool}> */
    public static function scoreStates(): iterable
    {
        yield 'confirmed score' => [PlayerScoreStatus::Confirmed, 6.0, true];
        yield 'pending score' => [PlayerScoreStatus::Pending, 6.0, false];
        yield 'did not play' => [PlayerScoreStatus::DidNotPlay, null, false];
        yield 'genuine zero score' => [PlayerScoreStatus::Confirmed, 0.0, true];
    }

    public function test_base_rating_is_required_for_a_playable_performance(): void
    {
        $score = PlayerScore::factory()->create(['base_rating' => null]);

        $this->assertFalse($score->isPlayable());
    }

    public function test_missing_score_is_not_playable(): void
    {
        $score = PlayerScore::factory()->make();

        $this->assertFalse(PlayerScore::isPlayableFor(
            $score->player_season_registration_id,
            $score->matchday_id,
        ));
    }

    public function test_invalid_status_cannot_be_assigned(): void
    {
        $this->expectException(ValueError::class);

        PlayerScore::factory()->make(['status' => 'invalid']);
    }
}
