<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\Matchday;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InitializeClassicChampionship
{
    public function handle(League $league, int $startMatchdayId): League
    {
        return DB::transaction(function () use ($league, $startMatchdayId): League {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->with('type')->firstOrFail();
            if ($league->type->key !== 'classic') {
                throw new ConflictHttpException('Championships can only be initialized for classic leagues.');
            }
            if ($league->hasInitializedClassicChampionship()) {
                throw new ConflictHttpException('The classic championship has already been initialized.');
            }
            $teamIds = $league->fantasyTeams()->lockForUpdate()->orderBy('id')->pluck('id');
            if ($teamIds->count() < 2) {
                throw new ConflictHttpException('At least two fantasy teams are required.');
            }
            $start = Matchday::query()->whereKey($startMatchdayId)->lockForUpdate()->firstOrFail();
            if ($start->season_id !== $league->season_id) {
                throw new ConflictHttpException('The starting matchday must belong to the league season.');
            }
            if (! $start->starts_at->isFuture()) {
                throw new ConflictHttpException('The starting matchday must not have started.');
            }
            $league->classicParticipants()->attach($teamIds);
            $league->forceFill(['classic_start_matchday_id' => $start->id, 'classic_started_at' => now()])->save();

            return $league->refresh();
        });
    }
}
