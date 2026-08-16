<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class FormulaOnePositionPoints implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $positions = [];

        foreach (array_keys($value) as $position) {
            if (filter_var($position, FILTER_VALIDATE_INT) === false || (int) $position < 1) {
                $fail("The {$attribute} positions must be positive integers.");

                return;
            }

            $positions[] = (int) $position;
        }

        sort($positions);

        if ($positions !== range(1, count($positions))) {
            $fail("The {$attribute} positions must be contiguous starting at 1.");

            return;
        }

        $previous = null;

        foreach ($positions as $position) {
            $points = $value[$position] ?? $value[(string) $position];

            if (! is_int($points) || $points < 0) {
                $fail("The {$attribute} points must be non-negative integers.");

                return;
            }

            if ($previous !== null && $points > $previous) {
                $fail("The {$attribute} points must be non-increasing by position.");

                return;
            }

            $previous = $points;
        }
    }
}
