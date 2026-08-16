<?php

namespace Tests\Feature\Filament;

use App\Http\Middleware\SetLocale;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminLocaleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Route::middleware(['web', SetLocale::class])->get('/_test/locale', fn() => response()->json([
            'locale' => app()->getLocale(),
            'label' => __('admin.locale_switcher.label'),
        ]));
    }

    public function test_authenticated_user_can_switch_each_supported_locale_and_is_redirected_back(): void
    {
        $user = $this->adminUser();

        foreach (config('admin.supported_locales') as $locale) {
            $this->actingAs($user)
                ->from('/admin')
                ->post(route('admin.locale.update', $locale))
                ->assertRedirect('/admin')
                ->assertSessionHas('locale', $locale);
        }
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.locale.update', 'fr'))
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_mutate_session(): void
    {
        $this->post(route('admin.locale.update', 'it'))
            ->assertForbidden()
            ->assertSessionMissing('locale');
    }

    public function test_regular_user_cannot_mutate_admin_locale_session(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('admin.locale.update', 'it'))
            ->assertForbidden()
            ->assertSessionMissing('locale');
    }

    public function test_middleware_applies_supported_session_locale_and_translations(): void
    {
        foreach (['en' => 'Language', 'de' => 'Sprache', 'it' => 'Lingua'] as $locale => $label) {
            $this->withSession(['locale' => $locale])->get('/_test/locale')
                ->assertOk()
                ->assertJson(['locale' => $locale, 'label' => $label]);
        }
    }

    public function test_middleware_uses_default_for_missing_or_invalid_session_locale(): void
    {
        $default = config('app.locale');

        $this->get('/_test/locale')->assertJson(['locale' => $default]);
        $this->withSession(['locale' => '../invalid'])->get('/_test/locale')->assertJson(['locale' => $default]);
    }

    public function test_supported_locales_are_centralized_and_translation_structures_match(): void
    {
        $this->assertSame(['en', 'de', 'it'], config('admin.supported_locales'));
        $keys = array_keys(Arr::dot(require lang_path('en/admin.php')));

        foreach (config('admin.supported_locales') as $locale) {
            $this->assertSame($keys, array_keys(Arr::dot(require lang_path("{$locale}/admin.php"))));
        }
    }

    public function test_selected_locale_survives_a_subsequent_authorized_panel_request(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->post(route('admin.locale.update', 'it'))
            ->assertSessionHas('locale', 'it');
        $this->get('/admin')->assertSuccessful()->assertSee('Lingua')->assertDontSee('admin.locale_switcher.label');
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'global_admin')->firstOrFail());

        return $user;
    }
}
