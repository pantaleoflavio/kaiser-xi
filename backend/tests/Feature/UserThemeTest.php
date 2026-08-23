<?php

namespace Tests\Feature;

use App\Enums\UserTheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_theme_is_persisted_and_returned_without_changing_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['name' => 'Kaiser', 'email' => 'kaiser@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/me', ['theme' => 'koenigsblau'])
            ->assertOk()
            ->assertJsonPath('data.theme', 'koenigsblau')
            ->assertJsonPath('data.name', 'Kaiser')
            ->assertJsonPath('data.email', 'kaiser@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Kaiser',
            'email' => 'kaiser@example.com',
            'theme' => 'koenigsblau',
        ]);
        $this->assertSame(UserTheme::Koenigsblau, $user->refresh()->theme);
    }

    public function test_unsupported_theme_is_rejected(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/me', ['theme' => 'arbitrary-theme'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('theme');

        $this->assertNull($user->refresh()->theme);
    }

    public function test_existing_user_without_theme_remains_valid_and_is_represented(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['theme' => null]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.theme', null);
    }
}
