<?php

namespace App\Services\League;

use App\Exceptions\ChampionshipParticipantsMissingTeamsException;
use App\Models\League;

final class AssertChampionshipParticipantsReady
{
    public function handle(League $league): void
    {
        $missingTeamCount = $league->users()
            ->whereDoesntHave('fantasyTeams', fn($query) => $query->where('league_id', $league->id))
            ->count();

        if ($missingTeamCount > 0) {
            throw new ChampionshipParticipantsMissingTeamsException($missingTeamCount);
        }
    }
}
