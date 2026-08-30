<?php

namespace Database\Factories;

use App\Models\RealCompetition;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        $startYear = $this->faker->numberBetween(2000, 2035);

        return [
            'real_competition_id' => RealCompetition::factory(),
            'name' => $startYear . '/' . ($startYear + 1),
            'starts_at' => now()->startOfYear()->toDateString(),
            'ends_at' => now()->endOfYear()->toDateString(),
            'is_active' => true,
        ];
    }
}
