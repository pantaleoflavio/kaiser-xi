<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FormationModuleRequirements\FormationModuleRequirementResource;
use App\Filament\Resources\Matchdays\MatchdayResource;
use App\Filament\Resources\PlayerScores\PlayerScoreResource;
use App\Filament\Resources\RealCompetitions\RealCompetitionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_key_resource_indexes(): void
    {
        $this->seedReferenceData();
        $this->actingAs($this->userWithRole('super_admin'));

        foreach ([RoleResource::class, RealCompetitionResource::class, MatchdayResource::class, PlayerScoreResource::class, FormationModuleRequirementResource::class] as $resource) {
            $this->get($resource::getUrl('index'))->assertSuccessful();
        }
    }

    public function test_global_admin_can_open_domain_resource_indexes(): void
    {
        $this->seedReferenceData();
        $this->actingAs($this->userWithRole('global_admin'));

        foreach ([RealCompetitionResource::class, MatchdayResource::class, PlayerScoreResource::class, FormationModuleRequirementResource::class] as $resource) {
            $this->get($resource::getUrl('index'))->assertSuccessful();
        }
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
