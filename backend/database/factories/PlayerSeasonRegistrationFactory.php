<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerRole;
use App\Models\PlayerSeasonRegistration;
use App\Models\SeasonClub;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerSeasonRegistration> */
class PlayerSeasonRegistrationFactory extends Factory
{
    protected $model = PlayerSeasonRegistration::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'season_club_id' => SeasonClub::factory(),
            'player_role_id' => PlayerRole::query()->inRandomOrder()->value('id') ?? PlayerRole::query()->create(['key' => 'factory_role_' . fake()->unique()->word(), 'label' => 'Factory role'])->id,
            'external_provider' => null,
            'external_id' => null,
            'shirt_number' => $this->faker->optional()->numberBetween(1, 99),
            'quotation' => $this->faker->randomFloat(2, 1, 100),
            'is_active' => true,
            'registered_on' => now()->toDateString(),
            'released_on' => null,
        ];
    }
}
