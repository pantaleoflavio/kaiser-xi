<?php

namespace App\Services\Standings;

use App\Models\FantasyMatch;
use App\Models\FantasyMatchResult;
use App\Models\League;
use App\Models\Standing;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CalculateHeadToHeadStandings
{
    /** @return Collection<int, Standing> */
    public function calculate(League $league): Collection
    {
        return DB::transaction(function () use ($league): Collection {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $league->loadMissing('type');

            if ($league->type?->key !== 'head_to_head') {
                throw new DomainException('Standings can only be calculated by this service for head-to-head leagues.');
            }

            if (! $league->hasInitializedHeadToHeadSchedule()) {
                throw new DomainException('The head-to-head schedule must be initialized before standings are calculated.');
            }

            $participantIds = FantasyMatch::query()
                ->where('league_id', $league->id)
                ->select('home_fantasy_team_id as fantasy_team_id')
                ->union(FantasyMatch::query()->where('league_id', $league->id)->select('away_fantasy_team_id as fantasy_team_id'))
                ->orderBy('fantasy_team_id')
                ->pluck('fantasy_team_id');

            /** @var array<int, array{played: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}> $statistics */
            $statistics = [];
            foreach ($participantIds as $teamId) {
                $statistics[(int) $teamId] = $this->emptyStatistics();
            }

            $results = FantasyMatchResult::query()
                ->join('fantasy_matches', 'fantasy_matches.id', '=', 'fantasy_match_results.fantasy_match_id')
                ->where('fantasy_matches.league_id', $league->id)
                ->where('fantasy_match_results.result_status', 'calculated')
                ->orderBy('fantasy_match_results.id')
                ->get([
                    'fantasy_matches.home_fantasy_team_id',
                    'fantasy_matches.away_fantasy_team_id',
                    'fantasy_match_results.home_goals',
                    'fantasy_match_results.away_goals',
                ]);

            foreach ($results as $result) {
                $homeId = (int) $result->home_fantasy_team_id;
                $awayId = (int) $result->away_fantasy_team_id;
                $homeGoals = (int) $result->home_goals;
                $awayGoals = (int) $result->away_goals;

                $this->recordResult($statistics[$homeId], $homeGoals, $awayGoals);
                $this->recordResult($statistics[$awayId], $awayGoals, $homeGoals);
            }

            $ranked = collect($statistics)
                ->map(fn(array $values, int $teamId): array => [
                    'fantasy_team_id' => $teamId,
                    ...$values,
                    'points_total' => ($values['wins'] * 3) + $values['draws'],
                ])
                ->sort(function (array $left, array $right): int {
                    return $right['points_total'] <=> $left['points_total']
                        ?: ($right['goals_for'] - $right['goals_against']) <=> ($left['goals_for'] - $left['goals_against'])
                        ?: $right['goals_for'] <=> $left['goals_for']
                        ?: $left['fantasy_team_id'] <=> $right['fantasy_team_id'];
                })->values();

            foreach ($ranked as $index => $values) {
                Standing::query()->updateOrCreate(
                    ['league_id' => $league->id, 'fantasy_team_id' => $values['fantasy_team_id']],
                    [...$values, 'position' => $index + 1, 'fantasy_points_total' => 0],
                );
            }

            return Standing::query()
                ->where('league_id', $league->id)
                ->whereIn('fantasy_team_id', $participantIds)
                ->with('fantasyTeam:id,name,slug')
                ->orderBy('position')
                ->orderBy('fantasy_team_id')
                ->get();
        });
    }

    /** @return array{played: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int} */
    private function emptyStatistics(): array
    {
        return ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0];
    }

    /** @param array{played: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int} $statistics */
    private function recordResult(array &$statistics, int $goalsFor, int $goalsAgainst): void
    {
        $statistics['played']++;
        $statistics['goals_for'] += $goalsFor;
        $statistics['goals_against'] += $goalsAgainst;

        if ($goalsFor > $goalsAgainst) {
            $statistics['wins']++;
        } elseif ($goalsFor === $goalsAgainst) {
            $statistics['draws']++;
        } else {
            $statistics['losses']++;
        }
    }
}
