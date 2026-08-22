<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\Matchday;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InitializeChampionship
{
    public function __construct(private readonly AssertChampionshipParticipantsReady $participantsReady) {}


    public function handle(League $league, int $startMatchdayId): League
    {
        return DB::transaction(function () use ($league, $startMatchdayId): League {
            $league = League::query()->whereKey($league->id)->lockForUpdate()->with('type')->firstOrFail();
            if (! in_array($league->type->key, ['classic', 'formula_one'], true)) {
                throw new ConflictHttpException('Championships are only available for classic and formula one leagues.');
            }
            if ($league->hasInitializedChampionship()) {
                throw new ConflictHttpException('The championship has already been initialized.');
            }
            $this->participantsReady->handle($league);
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
            $league->championshipParticipants()->attach($teamIds);
            $league->forceFill([
                'championship_start_matchday_id' => $start->id,
                'championship_started_at' => now(),
            ])->save();

            return $league->refresh();
        });
    }
}
