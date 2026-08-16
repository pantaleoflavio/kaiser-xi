<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\Matchday;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChampionshipMatchdays
{
    /** @return Builder<Matchday> */
    public function query(League $league): Builder
    {
        if (! $league->isNonHeadToHeadChampionship() || ! $league->hasInitializedChampionship()) {
            throw new NotFoundHttpException;
        }

        $start = $league->championshipStartMatchday()->firstOrFail();

        return Matchday::query()->where('season_id', $league->season_id)
            ->where('starts_at', '>=', $start->starts_at)
            ->where('number', '>=', $start->number);
    }

    public function counted(League $league): Builder
    {
        return $this->query($league)->where('ends_at', '<=', now());
    }

    public function contains(League $league, Matchday $matchday): bool
    {
        return $this->query($league)->whereKey($matchday->id)->exists();
    }
}
