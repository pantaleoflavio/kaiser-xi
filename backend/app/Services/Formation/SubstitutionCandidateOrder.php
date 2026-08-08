<?php

namespace App\Services\Formation;

use App\Models\FormationPlayer;
use Illuminate\Support\Collection;

class SubstitutionCandidateOrder
{
    /**
     * Order playable candidates for role-priority substitution consideration.
     *
     * Formation validity remains the responsibility of the future resolver. This
     * policy only makes the configured candidate preference deterministic.
     *
     * @param  Collection<int, FormationPlayer>  $playableBench
     * @return Collection<int, FormationPlayer>
     */
    public function rolePriority(
        Collection $playableBench,
        int $outgoingPlayerRoleId,
        bool $allowFormationChange,
    ): Collection {
        $ordered = $playableBench
            ->sortBy([
                ['position_index', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $sameRole = $ordered
            ->filter(fn (FormationPlayer $candidate): bool => $candidate->player_role_id === $outgoingPlayerRoleId)
            ->values();

        if (! $allowFormationChange) {
            return $sameRole;
        }

        return $sameRole
            ->concat($ordered->reject(fn (FormationPlayer $candidate): bool => $candidate->player_role_id === $outgoingPlayerRoleId))
            ->values();
    }
}