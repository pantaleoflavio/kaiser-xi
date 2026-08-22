<?php

namespace Database\Factories;

use App\Models\RealClub;
use App\Models\RealClubExternalIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RealClubExternalIdentity> */
class RealClubExternalIdentityFactory extends Factory
{
    protected $model = RealClubExternalIdentity::class;

    public function definition(): array
    {
        return [
            'real_club_id' => RealClub::factory(),
            'provider' => $this->faker->unique()->slug(),
            'external_id' => $this->faker->unique()->uuid(),
        ];
    }
}
