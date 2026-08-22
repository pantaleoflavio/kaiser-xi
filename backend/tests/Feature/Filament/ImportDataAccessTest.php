<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ImportData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_global_administrators_can_access_import_page(): void
    {
        $this->seedReferenceData();
        foreach (['super_admin', 'global_admin'] as $role) $this->actingAs($this->user($role))->get(ImportData::getUrl())->assertSuccessful();
        $this->actingAs($this->user('user'))->get(ImportData::getUrl())->assertForbidden();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        return $user;
    }
}
