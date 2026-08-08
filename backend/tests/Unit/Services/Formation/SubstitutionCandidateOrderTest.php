<?php

namespace Tests\Unit\Services\Formation;

use App\Models\FormationPlayer;
use App\Services\Formation\SubstitutionCandidateOrder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubstitutionCandidateOrderTest extends TestCase
{
    #[Test]
    public function same_role_is_preferred_over_an_earlier_different_role(): void
    {
        $candidates = $this->candidates([[1, 20], [2, 10]]);

        $this->assertSame([2, 1], $this->ids($candidates, 10, true));
    }

    #[Test]
    public function same_role_candidates_retain_bench_order(): void
    {
        $candidates = $this->candidates([[3, 10], [1, 10], [2, 10]]);

        $this->assertSame([1, 2, 3], $this->ids($candidates, 10, true));
    }

    #[Test]
    public function different_role_fallback_is_excluded_when_formation_change_is_disabled(): void
    {
        $candidates = $this->candidates([[1, 20], [2, 30]]);

        $this->assertSame([], $this->ids($candidates, 10, false));
    }

    #[Test]
    public function different_role_fallback_is_considered_in_bench_order_when_formation_change_is_enabled(): void
    {
        $candidates = $this->candidates([[3, 30], [1, 20], [2, 40]]);

        $this->assertSame([1, 2, 3], $this->ids($candidates, 10, true));
    }

    #[Test]
    public function ordering_uses_role_identity_and_not_a_global_role_priority(): void
    {
        $candidates = $this->candidates([[1, 40], [2, 10], [3, 20]]);

        $this->assertSame([3, 1, 2], $this->ids($candidates, 20, true));
    }

    /** @param list<array{int, int}> $slots */
    private function candidates(array $slots): Collection
    {
        return collect($slots)->map(fn (array $slot): FormationPlayer => new FormationPlayer([
            'position_index' => $slot[0],
            'player_role_id' => $slot[1],
        ]));
    }

    /** @param Collection<int, FormationPlayer> $candidates */
    private function ids(Collection $candidates, int $roleId, bool $allowFormationChange): array
    {
        return app(SubstitutionCandidateOrder::class)
            ->rolePriority($candidates, $roleId, $allowFormationChange)
            ->pluck('position_index')
            ->all();
    }
}