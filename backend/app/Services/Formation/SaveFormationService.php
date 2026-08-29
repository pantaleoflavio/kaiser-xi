<?php

namespace App\Services\Formation;

use App\Exceptions\LineupDeadlinePassedException;
use App\Models\FantasyTeam;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;

class SaveFormationService
{
    public function __construct(
        private AssertFormationEligibility $formationEligibility,
        private FormationPersistenceService $persistence,
    ) {}

    /** @param array{formation_module_id: int, starters: list<int>, bench: list<array{fantasy_team_player_id: int, order: int}>} $data */
    public function save(League $league, Matchday $matchday, FantasyTeam $team, array $data): Formation
    {
        $this->assertContext($league, $matchday, $team);
        $this->assertEligibility($league, $matchday);
        $this->assertBeforeDeadline($matchday);

        return $this->persistence->persist($league, $matchday, $team, $data);
    }

    public function assertBeforeDeadline(Matchday $matchday): void
    {
        if (now()->greaterThanOrEqualTo($matchday->lineupDeadline())) {
            throw new LineupDeadlinePassedException;
        }
    }

    public function assertEligibility(League $league, Matchday $matchday): void
    {
        $this->formationEligibility->assert($league, $matchday);
    }

    public function assertScheduleContains(League $league, Matchday $matchday): void
    {
        $this->formationEligibility->assertScheduleContains($league, $matchday);
    }

    private function assertContext(League $league, Matchday $matchday, FantasyTeam $team): void
    {
        abort_unless($team->league_id === $league->id && $matchday->season_id === $league->season_id, 404);
    }

    /** @return list<string> */
    public function relations(): array
    {
        return $this->persistence->relations();
    }
}
