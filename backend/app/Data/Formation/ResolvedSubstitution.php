<?php

namespace App\Data\Formation;

use App\Models\FormationModule;
use App\Models\FormationPlayer;

final readonly class ResolvedSubstitution
{
    public function __construct(
        public FormationPlayer $outgoing,
        public FormationPlayer $incoming,
        public int $incomingBenchPosition,
        public FormationModule $effectiveFormationModule,
    ) {}
}