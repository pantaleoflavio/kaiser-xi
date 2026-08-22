<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerExternalIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerExternalIdentity> */
class PlayerExternalIdentityFactory extends Factory
{
    protected $model = PlayerExternalIdentity::class;

    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'provider' => $this->faker->unique()->slug(),
            'external_id' => $this->faker->unique()->uuid(),
        ];
    }
}
