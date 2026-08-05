<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        Role::create(['name' => 'user']);
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mario', 'email' => 'mario@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);
        $response->assertCreated()->assertJsonPath('user.email', 'mario@example.com');
    }

    public function test_registered_user_receives_default_role(): void
    {
        $role = Role::create(['name' => 'user']);
        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $user = User::where('email', 'a@example.com')->firstOrFail();
        $this->assertTrue($user->roles->contains($role));
    }

    public function test_user_can_login_and_logout_and_me(): void
    {
        Role::create(['name' => 'user']);
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->roles()->attach(Role::firstWhere('name', 'user'));

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password123']);
        $login->assertOk()->assertJsonStructure(['token', 'user']);
        $token = $login->json('token');

        $tokenId = (int) str($token)->before('|')->toString();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => "Bearer {$token}",
        ])->assertUnauthorized();
    }

    public function test_global_admin_seed_exists(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->roles->contains('name', 'global_admin'));
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pw@example.com']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'pw@example.com'])->assertOk();
        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_authenticated_user_can_view_safe_account(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('password123'), 'remember_token' => 'secret']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.id', $user->id)->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at', 'created_at']]);
    }

    public function test_guest_cannot_view_or_update_account(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->patchJson('/api/v1/auth/me', ['name' => 'Guest'])->assertUnauthorized();
        $this->putJson('/api/v1/auth/me/password', ['current_password' => 'password', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertUnauthorized();
    }

    public function test_user_can_update_name_and_whitespace_is_trimmed(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['name' => '  New Name  '])->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_user_can_update_email_with_current_password_and_verification_is_reset(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now(), 'password' => Hash::make('password123')]);
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => 'new@example.com', 'current_password' => 'password123'])->assertOk()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.email_verified_at', null);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com', 'email_verified_at' => null]);
    }

    public function test_same_current_email_is_accepted(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'same@example.com', 'password' => Hash::make('password123')]);
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => 'same@example.com', 'current_password' => 'password123'])->assertOk()->assertJsonPath('data.email', 'same@example.com');
    }

    public function test_profile_update_rejects_duplicate_invalid_and_protected_fields(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => $other->email, 'current_password' => 'password123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => 'not-an-email', 'current_password' => 'password123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['id' => $other->id, 'roles' => ['global_admin'], 'password' => 'newpassword123'])->assertUnprocessable()->assertJsonValidationErrors(['id', 'roles', 'password']);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'email' => 'taken@example.com']);
    }

    public function test_current_password_is_required_and_checked_for_email_change(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'secure@example.com', 'password' => Hash::make('password123')]);
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => 'missing-password@example.com'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/auth/me', ['email' => 'wrong-password@example.com', 'current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'secure@example.com']);
    }

    public function test_user_can_change_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me/password', ['current_password' => 'password123', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertNoContent();
        $user->refresh();
        $this->assertFalse(Hash::check('password123', $user->password));
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertNotSame('newpassword123', $user->password);
    }

    public function test_password_update_validates_current_password_confirmation_and_rules(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me/password', ['current_password' => 'wrong', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me/password', ['current_password' => 'password123', 'password' => 'newpassword123'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me/password', ['current_password' => 'password123', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
