<?php

namespace App\Services\Matchday;

use App\Models\FantasyMatch;
use App\Models\Formation;
use App\Models\League;
use App\Models\LeagueMatchdayCalculation;
use App\Models\Matchday;
use App\Models\TeamMatchdayScore;
use App\Services\League\ChampionshipMatchdays;
use App\Services\Scoring\CalculateFantasyMatchResult;
use App\Services\Scoring\CalculateTeamMatchdayScore;
use App\Services\Standings\CalculateClassicStandings;
use App\Services\Standings\CalculateFormulaOneStandings;
use App\Services\Standings\CalculateHeadToHeadStandings;
use DomainException;
use Illuminate\Support\Facades\DB;

class FinalizeMatchday
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

    /**
     * Calculate (or recalculate) one league's completed matchday.
     *
     * Source data is deliberately left untouched. Existing derived rows are
     * replaced in the same transaction so a failure restores the prior result.
     */
    public function calculate(League $league, Matchday $matchday): void
    {
        if ((int) $league->season_id !== (int) $matchday->season_id) {
            throw new DomainException('The matchday does not belong to this league.');
        }

        if (now()->lt($matchday->ends_at)) {
            throw new DomainException('The matchday has not ended yet.');
        }

        $this->finalizeLeague($league->id, $matchday, true);
    }

    private function finalizeLeague(int $leagueId, Matchday $matchday, bool $manual = false): void
    {
        DB::transaction(function () use ($leagueId, $matchday, $manual): void {
            $league = League::query()
                ->with('type')
                ->lockForUpdate()
                ->findOrFail($leagueId);

            if ($manual && ! $this->isInitializedMatchday($league, $matchday)) {
                throw new DomainException('The matchday is not part of an initialized league competition.');
            }

            if ($league->isFormulaOne() && (
                ! $league->hasInitializedChampionship()
                || $matchday->ends_at->isFuture()
                || ! $this->championshipMatchdays->contains($league, $matchday)
            )) {
                return;
            }

            if ($manual) {
                FantasyMatch::query()
                    ->where('league_id', $league->id)
                    ->where('matchday_id', $matchday->id)
                    ->whereHas('result')
                    ->with('result')
                    ->each(fn(FantasyMatch $match): bool => (bool) $match->result?->delete());

                TeamMatchdayScore::query()
                    ->where('league_id', $league->id)
                    ->where('matchday_id', $matchday->id)
                    ->delete();
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
                if ($manual) {
                    $this->markCalculated($league, $matchday);
                }

                return;
            }

            if ($league->type?->key === 'formula_one' && $league->hasInitializedChampionship()) {
                $this->formulaOneStandings->calculate($league);
                if ($manual) {
                    $this->markCalculated($league, $matchday);
                }

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

            if ($manual) {
                $this->markCalculated($league, $matchday);
            }
        });
    }

    private function markCalculated(League $league, Matchday $matchday): void
    {
        LeagueMatchdayCalculation::query()->updateOrCreate(
            ['league_id' => $league->id, 'matchday_id' => $matchday->id],
            ['calculated_at' => now()],
        );
    }

    private function isInitializedMatchday(League $league, Matchday $matchday): bool
    {
        if ($league->isNonHeadToHeadChampionship()) {
            return $league->hasInitializedChampionship()
                && $this->championshipMatchdays->contains($league, $matchday);
        }

        return $league->type?->key === 'head_to_head'
            && $league->hasInitializedHeadToHeadSchedule()
            && FantasyMatch::query()
            ->where('league_id', $league->id)
            ->where('matchday_id', $matchday->id)
            ->exists();
    }
}
