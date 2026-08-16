<?php

namespace App\Services\Standings;

use App\Models\League;
use App\Models\Standing;
use App\Models\TeamMatchdayScore;
use App\Services\League\ChampionshipMatchdays;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class CalculateClassicStandings
{
    public function __construct(private readonly ChampionshipMatchdays $matchdays) {}

    /** @return Collection<int, Standing> */
    public function calculate(League $league): Collection
    {
        return DB::transaction(function () use ($league): Collection {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->with('type')->firstOrFail();
            if ($league->type->key !== 'classic') {
                throw new DomainException('Classic standings can only be calculated for classic leagues.');
            }
            if (! $league->hasInitializedClassicChampionship()) {
                throw new DomainException('The classic championship must be initialized first.');
            }
            $matchdayIds = $this->matchdays->counted($league)->orderBy('number')->pluck('id');
            $teamIds = $league->classicParticipants()->orderBy('fantasy_teams.id')->pluck('fantasy_teams.id');
            $scores = TeamMatchdayScore::query()->where('league_id', $league->id)->whereIn('fantasy_team_id', $teamIds)
                ->whereIn('matchday_id', $matchdayIds)->get()->keyBy(fn(TeamMatchdayScore $score): string => $score->fantasy_team_id . '-' . $score->matchday_id);
            $ranked = $teamIds->map(function (int $teamId) use ($matchdayIds, $scores): array {
                $values = $matchdayIds->map(fn(int $matchdayId): string => (string) ($scores->get($teamId . '-' . $matchdayId)?->points ?? '0'));
                $total = $values->reduce(fn(string $carry, string $value): string => bcadd($carry, $value, 2), '0');
                $played = $matchdayIds->count();
                return [
                    'fantasy_team_id' => $teamId,
                    'played' => $played,
                    'fantasy_points_total' => $total,
                    'average_points' => $played ? bcdiv($total, (string) $played, 4) : '0',
                    'best_matchday_score' => $played ? $values->sortDesc()->first() : '0'
                ];
            })->sort(fn(array $a, array $b): int => bccomp($b['fantasy_points_total'], $a['fantasy_points_total'], 2)
                ?: bccomp($b['average_points'], $a['average_points'], 4)
                ?: bccomp($b['best_matchday_score'], $a['best_matchday_score'], 2)
                ?: $a['fantasy_team_id'] <=> $b['fantasy_team_id'])->values();
            foreach ($ranked as $position => $values) {
                Standing::query()->updateOrCreate(
                    ['league_id' => $league->id, 'fantasy_team_id' => $values['fantasy_team_id']],
                    [...$values, 'position' => $position + 1, 'points_total' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0]
                );
            }
            return Standing::query()->where('league_id', $league->id)->whereIn('fantasy_team_id', $teamIds)
                ->with('fantasyTeam:id,name,slug')->orderBy('position')->get();
        });
    }
}
