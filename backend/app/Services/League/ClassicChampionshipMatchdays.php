<?php

namespace App\Services\League;

use App\Models\League;
use App\Models\Matchday;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClassicChampionshipMatchdays
{
    /** @return Builder<Matchday> */
    public function query(League $league): Builder
    {
        if (! $league->isClassic() || ! $league->hasInitializedClassicChampionship()) {
            throw new NotFoundHttpException;
        }

        $start = $league->classicStartMatchday()->firstOrFail();

        return Matchday::query()
            ->where('season_id', $league->season_id)
            ->where('starts_at', '>=', $start->starts_at)
            // Matchday numbers are the Season's championship sequence. The extra
            // guard keeps independently seeded, later-dated demo scenarios out.
            ->where('number', '>=', $start->number);
    }

    public function contains(League $league, Matchday $matchday): bool
    {
        return $this->query($league)->whereKey($matchday->id)->exists();
    }
}
