<?php

namespace Database\Seeders\Support;

use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Services\Formation\FormationPersistenceService;

/**
 * Constructs intentional demo fixture state without simulating a current user write.
 */
final class DemoFormationWriter
{
    public function __construct(private readonly FormationPersistenceService $persistence) {}

    /** @param array{formation_module_id: int, starters: list<int>, bench: list<array{fantasy_team_player_id: int, order: int}>} $data */
    public function save(League $league, Matchday $matchday, FantasyTeam $team, array $data): Formation
    {
        return $this->persistence->persist($league, $matchday, $team, $data);
    }

    public function submit(Formation $formation, Matchday $matchday): Formation
    {
        abort_unless($formation->matchday_id === $matchday->id, 404);
        $formation->update([
            'submitted_at' => now(),
            'is_confirmed' => true,
        ]);

        return $formation->load($this->persistence->relations());
    }
}
