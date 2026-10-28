<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use App\Support\Locales;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Language switcher: the dropdown in the topbar stores the UI language in
 * the account for signed-in users and the session for guests.
 */
class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * List the available locales discovered from lang/.
     *
     * Expects at least English and German with their native names.
     */
    public function test_available_locales_are_discovered_from_the_lang_directory(): void
    {
        $available = Locales::available();
        $this->assertSame('English', $available['en']);
        $this->assertSame('Deutsch', $available['de']);
        $this->assertTrue(Locales::isAvailable('de'));
        $this->assertFalse(Locales::isAvailable('xx'));
    }

    /**
     * Switch the language via the route and open the panel.
     *
     * Expects the locale to be stored in the session and the panel to render
     * in German afterwards.
     */
    public function test_switching_the_language_persists_in_the_session(): void
    {
        $this->get('/language/de')->assertRedirect();
        $this->assertSame('de', session('locale'));

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        Participant::create(['name' => 'Max']);
        $this->actingAs($admin);
        $this->withSession(['locale' => 'de'])->get('/admin')->assertSee('Monatsübersicht');
        $this->withSession(['locale' => 'en'])->get('/admin')->assertSee('Monthly overview');
    }

    /**
     * Switch to a locale that has no translations.
     *
     * Expects a 404 and no session change.
     */
    public function test_unknown_locales_are_rejected(): void
    {
        $this->get('/language/xx')->assertNotFound();
        $this->assertNull(session('locale'));
    }

    /** A chosen language survives logout and a fresh password login, independently of session state. */
    public function test_account_language_is_restored_after_a_new_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'a-long-test-password']);
        $this->actingAs($admin);
        $this->get('/language/de')->assertRedirect()->assertSessionHas('locale', 'de');
        $this->assertSame('de', $admin->fresh()->locale);
        Auth::logout();
        $this->flushSession();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'a-long-test-password'])
            ->call('authenticate')->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Monatsübersicht');
    }

    /** Switching updates only the authenticated account and each account overrides a different session locale. */
    public function test_each_account_keeps_its_own_last_selected_language(): void
    {
        $first = User::factory()->create(['is_admin' => true, 'locale' => 'de']);
        $second = User::factory()->create(['is_admin' => true, 'locale' => 'en']);

        $this->actingAs($first)->withSession(['locale' => 'en'])->get('/admin')->assertOk()->assertSee('Monatsübersicht');
        $this->actingAs($second)->withSession(['locale' => 'de'])->get('/admin')->assertOk()->assertSee('Monthly overview');
        $this->get('/language/de')->assertRedirect();
        $this->get('/language/en')->assertRedirect();

        $this->assertSame('de', $first->fresh()->locale);
        $this->assertSame('en', $second->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    /** Unsupported choices cannot replace an account preference or the current session language. */
    public function test_unknown_language_leaves_the_saved_preference_unchanged(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'locale' => 'de']);

        $this->actingAs($admin)->withSession(['locale' => 'de'])->get('/language/xx')->assertNotFound();

        $this->assertSame('de', $admin->fresh()->locale);
        $this->assertSame('de', session('locale'));
    }

    /** Removed translations fall back to an available session language. */
    public function test_unavailable_saved_language_uses_available_fallbacks(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'locale' => 'xx']);
        $this->actingAs($admin)->withSession(['locale' => 'de'])->get('/admin')->assertOk()->assertSee('Monatsübersicht');
    }

    /** With no available account or session choice, the configured default remains usable. */
    public function test_unavailable_account_and_session_languages_use_the_application_default(): void
    {
        config(['app.locale' => 'en']);
        $admin = User::factory()->create(['is_admin' => true, 'locale' => 'xx']);

        $this->actingAs($admin)->withSession(['locale' => 'xx'])->get('/admin')->assertOk()->assertSee('Monthly overview');
    }

    /**
     * Open the panel as an admin.
     *
     * Expects the language dropdown with the available languages next to the
     * user menu.
     */
    public function test_the_switcher_is_rendered_next_to_the_user_menu(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        Participant::create(['name' => 'Max']);
        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertSee(route('language.switch', 'de'), false)->assertSee('Deutsch');
    }
}
