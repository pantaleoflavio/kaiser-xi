<?php

namespace App\Services\Matchday;

use App\Models\FantasyMatch;
use App\Models\Formation;
use App\Models\League;
use App\Models\Matchday;
use App\Services\League\ChampionshipMatchdays;
use App\Services\Scoring\CalculateFantasyMatchResult;
use App\Services\Scoring\CalculateTeamMatchdayScore;
use App\Services\Standings\CalculateClassicStandings;
use App\Services\Standings\CalculateFormulaOneStandings;
use App\Services\Standings\CalculateHeadToHeadStandings;
use Illuminate\Support\Facades\DB;

final class FinalizeMatchday
{
    public function __construct(
        private readonly CalculateTeamMatchdayScore $teamScores,
        private readonly CalculateFantasyMatchResult $matchResults,
        private readonly CalculateHeadToHeadStandings $standings,
        private readonly CalculateClassicStandings $classicStandings,
        private readonly CalculateFormulaOneStandings $formulaOneStandings,
        private readonly ChampionshipMatchdays $championshipMatchdays,
    ) {}

    public function finalize(Matchday $matchday): void
    {
        League::query()
            ->where('season_id', $matchday->season_id)
            ->orderBy('id')
            ->pluck('id')
            ->each(fn(int $leagueId) => $this->finalizeLeague($leagueId, $matchday));
    }

    private function finalizeLeague(int $leagueId, Matchday $matchday): void
    {
        DB::transaction(function () use ($leagueId, $matchday): void {
            $league = League::query()
                ->with('type')
                ->lockForUpdate()
                ->findOrFail($leagueId);

            if ($league->isFormulaOne() && (
                ! $league->hasInitializedChampionship()
                || $matchday->ends_at->isFuture()
                || ! $this->championshipMatchdays->contains($league, $matchday)
            )) {
                return;
            }

            Formation::query()
                ->where('league_id', $league->id)
                ->where('matchday_id', $matchday->id)
                ->where('is_confirmed', true)
                ->whereNotNull('submitted_at')
                ->with('fantasyTeam')
                ->orderBy('fantasy_team_id')
                ->each(function (Formation $formation) use ($matchday): void {
                    $this->teamScores->calculate($formation->fantasyTeam, $matchday);
                });

            if ($league->type?->key === 'classic' && $league->hasInitializedClassicChampionship()) {
                $this->classicStandings->calculate($league);

                return;
            }

            if ($league->type?->key === 'formula_one' && $league->hasInitializedChampionship()) {
                $this->formulaOneStandings->calculate($league);

                return;
            }

            if (
                $league->type?->key !== 'head_to_head'
                || ! $league->hasInitializedHeadToHeadSchedule()
            ) {
                return;
            }

            FantasyMatch::query()
                ->where('league_id', $league->id)
                ->where('matchday_id', $matchday->id)
                ->orderBy('id')
                ->each(function (FantasyMatch $fantasyMatch): void {
                    $this->matchResults->calculate($fantasyMatch);
                });

            $this->standings->calculate($league);
        });
    }
}
