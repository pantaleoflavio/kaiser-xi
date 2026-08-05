<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\LeagueStatus;
use App\Models\LeagueType;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    protected $model = League::class;

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'league_type_id' => LeagueType::query()->firstOrCreate(['key' => 'classic'], ['label' => 'Classic'])->id,
            'league_status_id' => LeagueStatus::query()->firstOrCreate(['key' => LeagueStatus::ACTIVE], ['label' => 'Active'])->id,
            'commissioner_user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'max_participants' => 10,
        ];
    }
}
