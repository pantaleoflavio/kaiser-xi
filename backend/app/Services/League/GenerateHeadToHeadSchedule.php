<?php

namespace App\Services\League;

use App\Exceptions\LeagueScheduleAlreadyInitializedException;
use App\Models\FantasyMatch;
use App\Models\League;
use App\Models\Matchday;
use App\Services\League\HeadToHeadRoundRobinGenerator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GenerateHeadToHeadSchedule
{
    public function __construct(private readonly HeadToHeadRoundRobinGenerator $generator) {}

    public function handle(League $league, int $startMatchdayId): League
    {
        return DB::transaction(function () use ($league, $startMatchdayId): League {
            $lockedLeague = League::query()->whereKey($league->id)->lockForUpdate()->firstOrFail();
            $lockedLeague->load('type');

            if ($lockedLeague->hasInitializedHeadToHeadSchedule()) {
                throw new LeagueScheduleAlreadyInitializedException;
            }

            if ($lockedLeague->type->key !== 'head_to_head') {
                throw new ConflictHttpException('Schedules can only be initialized for head-to-head leagues.');
            }

            $teamIds = $lockedLeague->fantasyTeams()->lockForUpdate()->orderBy('id')->pluck('id')->all();
            if (count($teamIds) < 2) {
                throw new ConflictHttpException('At least two fantasy teams are required.');
            }

            $start = Matchday::query()->whereKey($startMatchdayId)->lockForUpdate()->firstOrFail();
            if ($start->season_id !== $lockedLeague->season_id) {
                throw new ConflictHttpException('The starting matchday must belong to the league season.');
            }
            if (! $start->starts_at->isFuture()) {
                throw new ConflictHttpException('The starting matchday must not have started.');
            }

            $matchdays = Matchday::query()
                ->where('season_id', $lockedLeague->season_id)
                ->where('number', '>=', $start->number)
                ->where('starts_at', '>=', $start->starts_at)
                ->orderBy('number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $cycle = $this->generator->doubleRoundRobin($teamIds);

            foreach ($matchdays as $index => $matchday) {
                foreach ($cycle[$index % count($cycle)] as $pair) {
                    FantasyMatch::query()->create([
                        'league_id' => $lockedLeague->id,
                        'matchday_id' => $matchday->id,
                        'home_fantasy_team_id' => $pair['home'],
                        'away_fantasy_team_id' => $pair['away'],
                    ]);
                }
            }

            $lockedLeague->forceFill([
                'h2h_start_matchday_id' => $start->id,
                'h2h_schedule_generated_at' => now(),
            ])->save();

            return $lockedLeague->refresh();
        });
    }
}
