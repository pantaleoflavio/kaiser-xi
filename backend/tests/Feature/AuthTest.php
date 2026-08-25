<?php

namespace Tests\Feature;

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\LeagueStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GlobalAdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
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
            'name' => 'Mario',
            'email' => 'mario@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
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

    public function test_registration_creates_unverified_user_and_sends_verification(): void
    {
        Notification::fake();
        Role::create(['name' => 'user']);
        $this->postJson('/api/v1/auth/register', ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated()->assertJsonPath('user.email_verified_at', null);
        $user = User::firstWhere('email', 'new@example.com');
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_email_verification_succeeds(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['user' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_unverified_user_cannot_access_application_endpoints(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/seasons')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_sensitive_auth_routes_are_rate_limited(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('password123')]);
        foreach (range(1, 5) as $_) $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();

        foreach (range(1, 3) as $_) $this->postJson('/api/v1/auth/forgot-password', ['email' => 'absent@example.com']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'absent@example.com'])->assertTooManyRequests();
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        foreach (range(1, 3) as $_) $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/email/verification-notification')->assertOk();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/email/verification-notification')->assertTooManyRequests();
    }

    public function test_password_reset_and_change_revoke_all_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'revoke@example.com', 'password' => Hash::make('password123')]);
        $user->createToken('one');
        $user->createToken('two');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'])->assertOk();
        $this->assertCount(0, $user->tokens()->get());

        $token = $user->createToken('current')->plainTextToken;
        $this->withToken($token)->putJson('/api/v1/auth/me/password', ['current_password' => 'newpassword123', 'password' => 'thirdpassword123', 'password_confirmation' => 'thirdpassword123'])->assertNoContent();
        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_normal_account_is_anonymized_and_tokens_revoked(): void
    {
        $user = User::factory()->create(['email' => 'personal@example.com', 'name' => 'Personal Name', 'password' => Hash::make('password123')]);
        $token = $user->createToken('current')->plainTextToken;
        $this->withToken($token)->deleteJson('/api/v1/auth/me', ['current_password' => 'password123', 'confirmation' => true])->assertOk();
        $user->refresh();
        $this->assertSame('Deleted user', $user->name);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertCount(0, $user->tokens()->get());
        $this->postJson('/api/v1/auth/login', ['email' => 'personal@example.com', 'password' => 'password123'])->assertUnprocessable();
    }

    public function test_active_league_commissioner_must_resolve_ownership_before_deletion(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        League::factory()->for($user, 'commissioner')->create();
        $token = $user->createToken('current')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/me', ['current_password' => 'password123', 'confirmation' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_completed_league_history_remains_after_commissioner_anonymization(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $status = LeagueStatus::query()->create(['key' => LeagueStatus::COMPLETED, 'label' => 'Completed']);
        $league = League::factory()->for($user, 'commissioner')->create(['league_status_id' => $status->id]);
        $team = FantasyTeam::factory()->for($league)->for($user)->create();
        $token = $user->createToken('current')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/me', ['current_password' => 'password123', 'confirmation' => true])->assertOk();
        $this->assertDatabaseHas('leagues', ['id' => $league->id, 'commissioner_user_id' => $user->id]);
        $this->assertDatabaseHas('fantasy_teams', ['id' => $team->id, 'user_id' => $user->id]);
    }

    public function test_expired_sanctum_token_is_rejected(): void
    {
        config(['sanctum.expiration' => 1]);
        $user = User::factory()->create();
        $token = $user->createToken('expired')->plainTextToken;
        $user->tokens()->update(['created_at' => now()->subMinutes(2)]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }


    public function test_global_admin_seed_exists(): void
    {
        $this->seed([
            RoleSeeder::class,
            GlobalAdminSeeder::class,
        ]);

        $admin = User::query()
            ->where('email', 'admin@example.com')
            ->first();

        $this->assertNotNull($admin);
        $this->assertTrue(
            $admin->roles->contains('name', 'global_admin')
        );
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
