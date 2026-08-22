<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PlayerScores\PlayerScoreResource;
use App\Filament\Resources\RealCompetitions\RealCompetitionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_super_admin_can_access_admin_panel_and_system_resources(): void
    {
        $user = $this->userWithRole('super_admin');

        $this->actingAs($user)->get('/admin')->assertSuccessful();
        $this->actingAs($user)->get(UserResource::getUrl('index'))->assertSuccessful();
        $this->actingAs($user)->get(RoleResource::getUrl('index'))->assertSuccessful();
        $this->actingAs($user)->get(PlayerScoreResource::getUrl('index'))->assertSuccessful();
    }

    public function test_global_admin_can_access_panel_and_domain_resources_but_not_system_resources(): void
    {
        $user = $this->userWithRole('global_admin');

        $this->actingAs($user)->get('/admin')->assertSuccessful();
        $this->actingAs($user)->get(RealCompetitionResource::getUrl('index'))->assertSuccessful();
        $this->actingAs($user)->get(PlayerScoreResource::getUrl('index'))->assertSuccessful();
        $this->actingAs($user)->get(UserResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(RoleResource::getUrl('index'))->assertForbidden();
    }

    public function test_regular_user_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->userWithRole('user'))->get('/admin')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_filament_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
