<?php

namespace Tests\Feature\Domain;

use App\Enums\PlayerScoreStatus;
use App\Models\Matchday;
use App\Models\PlayerScore;
use App\Models\PlayerSeasonRegistration;
use App\Models\Season;
use App\Services\PlayerScore\PlayerScoreService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerScoreValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_requires_a_base_rating_but_not_a_final_score(): void
    {
        $service = app(PlayerScoreService::class);
        $service->prepare($this->validInput(['status' => PlayerScoreStatus::Confirmed, 'base_rating' => 7.25, 'final_score' => null]));
        $service->prepare($this->validInput(['status' => PlayerScoreStatus::Pending, 'final_score' => null]));

        $this->expectException(ValidationException::class);
        $service->prepare($this->validInput(['status' => PlayerScoreStatus::Confirmed, 'base_rating' => null]));
    }

    public function test_missing_performance_values_receive_database_compatible_defaults(): void
    {
        $data = $this->validInput();
        unset($data['clean_sheet'], $data['is_captain']);
        foreach (PlayerScoreService::EVENT_FIELDS as $field) {
            unset($data[$field]);
        }

        $prepared = app(PlayerScoreService::class)->prepare($data);
        $score = PlayerScore::query()->create($prepared);

        foreach (PlayerScoreService::EVENT_FIELDS as $field) {
            $this->assertSame(0, $score->{$field});
        }
        $this->assertFalse($score->clean_sheet);
        $this->assertFalse($score->is_captain);
    }

    public function test_did_not_play_normalizes_an_existing_performance(): void
    {
        $score = PlayerScore::factory()->captain()->confirmed(12)->create([
            'base_rating' => 8.5,
            'goals' => 2,
            'assists' => 1,
            'clean_sheet' => true,
        ]);

        $prepared = app(PlayerScoreService::class)->prepare([
            'status' => PlayerScoreStatus::DidNotPlay->value,
        ], $score);
        $score->update($prepared);
        $score->refresh();

        $this->assertNull($score->base_rating);
        $this->assertNull($score->final_score);
        foreach (PlayerScoreService::EVENT_FIELDS as $field) {
            $this->assertSame(0, $score->{$field});
        }
        $this->assertFalse($score->clean_sheet);
        $this->assertFalse($score->is_captain);
    }

    #[DataProvider('invalidEventValues')]
    public function test_invalid_event_values_are_rejected(string $field, int|float $value): void
    {
        try {
            app(PlayerScoreService::class)->prepare($this->validInput([$field => $value]));
            $this->fail("{$field} value {$value} should have been rejected.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /** @return iterable<string, array{string, int|float}> */
    public static function invalidEventValues(): iterable
    {
        foreach (PlayerScoreService::EVENT_FIELDS as $field) {
            yield "{$field} negative" => [$field, -1];
            yield "{$field} fractional" => [$field, 1.5];
        }
    }

    public function test_cross_season_identity_is_rejected(): void
    {
        $data = $this->validInput();

        $registration = PlayerSeasonRegistration::query()
            ->with('seasonClub')
            ->findOrFail($data['player_season_registration_id']);

        $differentSeason = Season::factory()->create();

        $differentSeasonMatchday = Matchday::factory()->create([
            'season_id' => $differentSeason->id,
        ]);

        $this->assertNotSame(
            $registration->seasonClub->season_id,
            $differentSeasonMatchday->season_id,
        );

        $this->expectException(ValidationException::class);

        app(PlayerScoreService::class)->prepare([
            ...$data,
            'matchday_id' => $differentSeasonMatchday->id,
        ]);
    }

    public function test_duplicate_identity_is_rejected_with_validation_feedback(): void
    {
        $score = PlayerScore::factory()->create();

        try {
            app(PlayerScoreService::class)->prepare($this->validInput([
                'player_season_registration_id' => $score->player_season_registration_id,
                'matchday_id' => $score->matchday_id,
            ]));
            $this->fail('Duplicate score should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player_season_registration_id', $exception->errors());
        } catch (QueryException) {
            $this->fail('Duplicate identity reached the database unique constraint.');
        }
    }

    public function test_matchday_label_uses_number_when_name_is_missing(): void
    {
        $matchday = Matchday::factory()->create(['number' => 7, 'name' => null]);

        $this->assertSame('7', $matchday->displayLabel());
        $matchday->name = 'Derby';
        $this->assertSame('7 — Derby', $matchday->displayLabel());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge(PlayerScore::factory()->make()->getAttributes(), $overrides);
    }
}
