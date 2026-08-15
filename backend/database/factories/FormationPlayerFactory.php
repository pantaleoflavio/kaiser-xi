<?php

namespace Database\Factories;

use App\Models\FantasyTeamPlayer;
use App\Models\Formation;
use App\Models\FormationPlayer;
use App\Models\PlayerRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormationPlayer>
 */
class FormationPlayerFactory extends Factory
{
    protected $model = FormationPlayer::class;

    public function definition(): array
    {
        $formation = Formation::factory()->create();
        $assignment = FantasyTeamPlayer::factory()->create([
            'league_id' => $formation->league_id,
            'fantasy_team_id' => $formation->fantasy_team_id,
        ]);

        return [
            'formation_id' => $formation->id,
            'fantasy_team_player_id' => $assignment->id,
            'player_id' => $assignment->player_id,
            'player_role_id' => PlayerRole::query()->firstOrCreate(
                ['key' => 'defender'],
                ['label' => 'Defender', 'sort_order' => 2],
            )->id,
            'slot_type' => 'starter',
            'position_index' => $this->faker->numberBetween(1, 11),
        ];
    }
}
