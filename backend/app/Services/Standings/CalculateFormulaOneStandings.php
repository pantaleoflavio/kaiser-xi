<?php

namespace App\Services\Standings;

use App\Data\Standings\FormulaOnePlacement;
use App\Models\League;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Services\League\ChampionshipMatchdays;
use App\Services\Standings\RankFormulaOneMatchday;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CalculateFormulaOneStandings
{
    public function __construct(
        private readonly ChampionshipMatchdays $matchdays,
        private readonly RankFormulaOneMatchday $dailyRanking,
    ) {}

    /** @return Collection<int, Standing> */
    public function calculate(League $league): Collection
    {
        return DB::transaction(function () use ($league): Collection {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->with('type')->firstOrFail();
            if ($league->type->key !== 'formula_one') {
                throw new DomainException('Formula One standings can only be calculated for formula one leagues.');
            }
            if (! $league->hasInitializedChampionship()) {
                throw new DomainException('The formula one championship must be initialized first.');
            }

            $teamIds = $league->championshipParticipants()->orderBy('fantasy_teams.id')->pluck('fantasy_teams.id');
            $matchdayIds = $this->matchdays->counted($league)->orderBy('number')->pluck('id');
            $scores = TeamMatchdayScore::query()->where('league_id', $league->id)
                ->whereIn('fantasy_team_id', $teamIds)->whereIn('matchday_id', $matchdayIds)->get()
                ->keyBy(fn(TeamMatchdayScore $score): string => "{$score->fantasy_team_id}-{$score->matchday_id}");
            $pointsTable = $league->formulaOnePositionPoints();
            $placements = collect();

            foreach ($matchdayIds as $matchdayId) {
                $dailyScores = $teamIds->mapWithKeys(fn(int $teamId): array => [
                    $teamId => (string) ($scores->get("{$teamId}-{$matchdayId}")?->points ?? '0.00'),
                ]);
                $placements->push(...$this->dailyRanking->rank($teamIds, $matchdayId, $dailyScores, $pointsTable));
            }

            $ranked = $teamIds->map(function (int $teamId) use ($placements, $matchdayIds): array {
                $team = $placements->where('fantasyTeamId', $teamId);
                $total = $team->reduce(fn(string $carry, FormulaOnePlacement $item): string => bcadd($carry, $item->fantasyPoints, 2), '0.00');
                $played = $matchdayIds->count();
                return [
                    'fantasy_team_id' => $teamId,
                    'played' => $played,
                    'championship_points' => $team->sum('championshipPoints'),
                    'wins' => $team->where('position', 1)->count(),
                    'podiums' => $team->where('position', '<=', 3)->count(),
                    'best_finish' => $played ? $team->min('position') : null,
                    'fantasy_points_total' => $total,
                    'average_points' => $played ? bcdiv($total, (string) $played, 4) : '0.0000',
                ];
            })->sort(function (array $a, array $b): int {
                return $b['championship_points'] <=> $a['championship_points']
                    ?: $b['wins'] <=> $a['wins']
                    ?: $b['podiums'] <=> $a['podiums']
                    ?: (($a['best_finish'] ?? PHP_INT_MAX) <=> ($b['best_finish'] ?? PHP_INT_MAX))
                    ?: bccomp($b['fantasy_points_total'], $a['fantasy_points_total'], 2)
                    ?: $a['fantasy_team_id'] <=> $b['fantasy_team_id'];
            })->values();

            Standing::query()->where('league_id', $league->id)->whereNotIn('fantasy_team_id', $teamIds)->delete();
            foreach ($ranked as $index => $values) {
                Standing::query()->updateOrCreate(
                    ['league_id' => $league->id, 'fantasy_team_id' => $values['fantasy_team_id']],
                    [
                        ...$values,
                        'position' => $index + 1,
                        'points_total' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'best_matchday_score' => 0
                    ]
                );
            }

            return Standing::query()->where('league_id', $league->id)->with('fantasyTeam:id,name,slug')->orderBy('position')->get();
        });
    }

    /** @return \Illuminate\Support\Collection<int, FormulaOnePlacement> */
    public function placementsFor(League $league, int $matchdayId): \Illuminate\Support\Collection
    {
        if (! $league->isFormulaOne() || ! $league->hasInitializedChampionship()) {
            throw new DomainException('Formula One placements require an initialized formula one championship.');
        }
        $teamIds = $league->championshipParticipants()->orderBy('fantasy_teams.id')->pluck('fantasy_teams.id');
        $scores = TeamMatchdayScore::query()->where('league_id', $league->id)->where('matchday_id', $matchdayId)
            ->whereIn('fantasy_team_id', $teamIds)->pluck('points', 'fantasy_team_id');

        return $this->dailyRanking->rank($teamIds, $matchdayId, $scores, $league->formulaOnePositionPoints());
    }
}
