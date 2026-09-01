<?php

namespace App\Services\Matchday;

use App\Models\Matchday;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UnlockMatchdayCalculation
{
    public function unlock(Matchday $matchday, User $actor): Matchday
    {
        if (! $actor->canAccessAdminPanel()) throw new AuthorizationException;

        return DB::transaction(function () use ($matchday, $actor): Matchday {
            $locked = Matchday::query()->lockForUpdate()->findOrFail($matchday->id);
            if ($locked->calculation_unlocked_at !== null) return $locked;
            if (now()->lt($locked->ends_at)) throw new DomainException(__('admin.resources.matchdays.unlock_not_ended'));
            $locked->update(['calculation_unlocked_at' => now(), 'calculation_unlocked_by_user_id' => $actor->id]);
            return $locked;
        });
    }
}
