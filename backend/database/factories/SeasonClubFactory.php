<?php

namespace Database\Factories;

use App\Models\RealClub;
use App\Models\Season;
use App\Models\SeasonClub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonClub>
 */
class SeasonClubFactory extends Factory
{
    protected $model = SeasonClub::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'real_club_id' => RealClub::factory(),
            'display_name' => null,
            'external_provider' => null,
            'external_id' => null,
            'is_active' => true,
        ];
    }
}
