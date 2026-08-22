<?php

namespace Tests\Unit\Rules;

use App\Rules\FormulaOnePositionPoints;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormulaOnePositionPointsTest extends TestCase
{
    public function test_it_accepts_contiguous_non_increasing_position_maps(): void
    {
        self::assertNull($this->validationError([3 => 15, 1 => 25, 2 => 18]));
        self::assertNull($this->validationError([1 => 25, 2 => 18]));
        self::assertNull($this->validationError([1 => 0]));
    }

    #[DataProvider('invalidPositionMaps')]
    public function test_it_rejects_invalid_position_maps(array $positions, string $message): void
    {
        self::assertSame($message, $this->validationError($positions));
    }

    public static function invalidPositionMaps(): array
    {
        return [
            'non-positive position' => [
                [0 => 25, 1 => 18],
                'The formula_one_position_points positions must be positive integers.',
            ],
            'non-integer position' => [
                ['first' => 25],
                'The formula_one_position_points positions must be positive integers.',
            ],
            'gap' => [
                [1 => 25, 3 => 15],
                'The formula_one_position_points positions must be contiguous starting at 1.',
            ],
            'negative points' => [
                [1 => 25, 2 => -1],
                'The formula_one_position_points points must be non-negative integers.',
            ],
            'non-integer points' => [
                [1 => 25, 2 => 18.5],
                'The formula_one_position_points points must be non-negative integers.',
            ],
            'increasing points' => [
                [1 => 10, 2 => 15],
                'The formula_one_position_points points must be non-increasing by position.',
            ],
        ];
    }

    private function validationError(array $positions): ?string
    {
        $message = null;

        (new FormulaOnePositionPoints)->validate(
            'formula_one_position_points',
            $positions,
            function (string $error) use (&$message): void {
                $message = $error;
            },
        );

        return $message;
    }
}
