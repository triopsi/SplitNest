<?php

namespace Tests\Feature;

use App\Filament\Auth\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Spatie\LaravelPasskeys\Livewire\PasskeysComponent;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

/**
 * Passkeys: users register one or more passkeys on the profile page and log
 * in passwordless via the button on the login page.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    /**
     * Inspect the user model.
     *
     * Expects it to implement the passkey contract and to own passkeys.
     */
    #[Group('critical')]
    public function test_users_own_passkeys(): void
    {
        $user = $this->admin();
        $this->assertInstanceOf(HasPasskeys::class, $user);
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id, 'name' => 'Mein 1Password']);
        $this->assertCount(1, $user->passkeys);
        $this->assertSame('Mein 1Password', $user->passkeys->first()->name);
    }

    /**
     * Open the login page as a guest (with an existing account, so the login
     * page does not forward to the first-run registration).
     *
     * Expects the passkey login form/button to be rendered.
     */
    #[Group('critical')]
    public function test_login_page_shows_the_passkey_button(): void
    {
        $script = $this->expectPasskeyScript();
        User::factory()->create();
        $this->get('/admin/login')->assertOk()->assertSee('passkey-login-form', false)->assertSee($script, false);
    }

    /**
     * Call the passkey authentication-options endpoint.
     *
     * Expects a JSON WebAuthn options response (the ceremony itself needs a
     * real authenticator and cannot run in tests).
     */
    public function test_passkey_authentication_options_route_responds_with_json(): void
    {
        $response = $this->getJson('/passkeys/authentication-options')->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * Open the profile page as an admin.
     *
     * Expects the standalone profile with account and passkey tabs, including
     * the passkey management component and its registration script.
     */
    public function test_profile_page_shows_passkey_management(): void
    {
        $script = $this->expectPasskeyScript();
        $this->assertTrue(Profile::isSimple());
        $this->actingAs($this->admin());
        $this->get(Profile::getUrl())
            ->assertOk()
            ->assertSee('role="tab"', false)
            ->assertSee('Account')
            ->assertSee('Passkeys')
            ->assertSeeLivewire(PasskeysComponent::class)
            ->assertSee($script, false);
    }

    /**
     * Expect the panel to render the Vite entry point without requiring built assets.
     */
    private function expectPasskeyScript(): string
    {
        $script = '<script type="module" src="/build/assets/passkeys-test.js"></script>';

        Vite::partialMock()->shouldReceive('__invoke')
            ->once()
            ->with('resources/js/app.js')
            ->andReturn(new HtmlString($script));

        return $script;
    }

    /**
     * Delete an own and a foreign passkey through the management component.
     *
     * Expects the own passkey to be deleted and the foreign one to stay.
     */
    #[Group('critical')]
    public function test_users_can_delete_only_their_own_passkeys(): void
    {
        $user = $this->admin();
        $other = $this->admin();
        $own = Passkey::factory()->create(['authenticatable_id' => $user->id]);
        $foreign = Passkey::factory()->create(['authenticatable_id' => $other->id]);

        Livewire::actingAs($user)->test(PasskeysComponent::class)
            ->call('deletePasskey', $own->id)
            ->call('deletePasskey', $foreign->id);

        $this->assertNull(Passkey::find($own->id));
        $this->assertNotNull(Passkey::find($foreign->id));
    }

    /**
     * Check the passkey config.
     *
     * Expects logins via passkey to redirect into the panel.
     */
    public function test_passkey_login_redirects_into_the_panel(): void
    {
        $this->assertSame('/admin', config('passkeys.redirect_to_after_login'));
    }
}
