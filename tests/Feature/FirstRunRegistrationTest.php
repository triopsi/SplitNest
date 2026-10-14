<?php

namespace Tests\Feature;

use App\Filament\Auth\FirstUserRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * First-run registration: while the users table is empty, guests are sent
 * to a registration form that creates the first administrator account.
 */
class FirstRunRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open the app as a guest while no account exists.
     *
     * Expects the panel to redirect to the login and the login to forward to
     * the first-user registration form.
     */
    #[Group('critical')]
    public function test_guest_is_sent_to_first_user_registration_when_no_account_exists(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertRedirect('/admin/register');
        $this->get('/admin/register')->assertOk();
    }

    /**
     * Register the first account through the web form.
     *
     * Expects an admin user with a hashed password, an authenticated session
     * and a redirect into the panel.
     */
    #[Group('critical')]
    public function test_first_user_can_register_via_the_web_form(): void
    {
        Livewire::test(FirstUserRegister::class)
            ->set('data.name', 'Daniel')
            ->set('data.email', 'Daniel@Example.Test')
            ->set('data.password', 'A-long-test-password!')
            ->set('data.passwordConfirmation', 'A-long-test-password!')
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('daniel@example.test', $user->email);
        $this->assertTrue(Hash::check('A-long-test-password!', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Open the registration page and submit the form when an account already exists.
     *
     * Expects a redirect to the login and no second account to be created —
     * the guard in handleRegistration also blocks a form that was opened
     * while the table was still empty and submitted after the first account
     * appeared.
     */
    #[Group('critical')]
    public function test_registration_is_blocked_once_an_account_exists(): void
    {
        $component = Livewire::test(FirstUserRegister::class);
        User::factory()->create();

        $this->get('/admin/register')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();

        $component
            ->set('data.name', 'Zweiter')
            ->set('data.email', 'zweiter@example.test')
            ->set('data.password', 'A-long-test-password!')
            ->set('data.passwordConfirmation', 'A-long-test-password!')
            ->call('register')
            ->assertHasErrors(['email']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Open the login page once an account exists.
     *
     * Expects the login form to render without a link to the registration.
     */
    public function test_login_page_hides_the_register_link_when_accounts_exist(): void
    {
        User::factory()->create();
        $this->get('/admin/login')->assertOk()->assertDontSee('/admin/register');
    }
}
