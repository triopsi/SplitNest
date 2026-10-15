<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Language switcher: the dropdown in the topbar stores the UI language in
 * the session, on top of the APP_LOCALE default.
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
        Participant::create(['name' => 'Daniel']);
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
        Participant::create(['name' => 'Daniel']);
        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertSee(route('language.switch', 'de'), false)->assertSee('Deutsch');
    }
}
