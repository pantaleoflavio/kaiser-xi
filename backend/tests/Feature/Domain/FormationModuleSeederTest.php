<?php

namespace Tests\Feature\Domain;

use App\Models\FormationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationModuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_433_module_has_correct_role_requirements(): void
    {
        $this->seed();
        $this->seed();

        $module = FormationModule::query()->where('name', '4-3-3')->firstOrFail();
        $requirements = $module->requirements()
            ->with('playerRole')
            ->get()
            ->mapWithKeys(fn ($requirement) => [
                $requirement->playerRole->key => $requirement->required_count,
            ])
            ->all();

        $this->assertSame([
            'goalkeeper' => 1,
            'defender' => 4,
            'midfielder' => 3,
            'forward' => 3,
        ], $requirements);
        $this->assertSame(7, FormationModule::query()->count());
        $this->assertSame(11, $module->requiredPlayersCount());
    }
}
