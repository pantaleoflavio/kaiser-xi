<?php

namespace App\Data\Formation;

use App\Models\FormationModule;
use App\Models\FormationPlayer;
use Illuminate\Support\Collection;

final readonly class ResolvedFormation
{
    /**
     * @param  Collection<int, FormationPlayer>  $effectiveStarters
     * @param  Collection<int, FormationPlayer>  $unusedBench
     * @param  Collection<int, ResolvedSubstitution>  $substitutions
     * @param  Collection<int, FormationPlayer>  $unavailableStartersNotReplaced
     */
    public function __construct(
        public FormationModule $originalFormationModule,
        public FormationModule $effectiveFormationModule,
        public Collection $effectiveStarters,
        public Collection $unusedBench,
        public Collection $substitutions,
        public Collection $unavailableStartersNotReplaced,
    ) {}
}
