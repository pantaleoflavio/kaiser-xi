<?php

namespace Database\Factories;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FantasyTeam>
 */
class FantasyTeamFactory extends Factory
{
    protected $model = FantasyTeam::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_path' => null,
            'budget' => null,
            'remaining_budget' => null,
        ];
    }

    public function forLeagueAndUser(League $league, User $user): static
    {
        return $this->state(fn () => [
            'league_id' => $league->id,
            'user_id' => $user->id,
        ]);
    }
}
