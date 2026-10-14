<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Password reset flow: request a reset link by e-mail and set a new
 * password with the mailed token.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open the password reset request page and the login page as a guest.
     *
     * Expects the request page to render and the login page to link to it.
     */
    public function test_password_reset_pages_are_reachable_and_linked_from_login(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
        $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request');
    }

    /**
     * Request a reset link for an existing account.
     *
     * Expects a ResetPassword notification, a row in password_reset_tokens and
     * the token expiry to default to 24 hours (1440 minutes).
     */
    public function test_reset_link_is_sent_and_the_token_defaults_to_24_hours(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->assertSame(1440, (int) config('auth.passwords.users.expire'));

        $status = Password::sendResetLink(['email' => $user->email]);
        $this->assertSame(Password::RESET_LINK_SENT, $status);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Reset the password with a valid token from the notification.
     *
     * Expects the reset to succeed and the new password to be stored hashed.
     */
    #[Group('critical')]
    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Password::sendResetLink(['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $status = Password::reset(
            ['email' => $user->email, 'password' => 'Ein-neues-langes-Passwort!', 'password_confirmation' => 'Ein-neues-langes-Passwort!', 'token' => $token],
            fn (User $u, string $password) => $u->forceFill(['password' => $password])->save(),
        );
        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(Hash::check('Ein-neues-langes-Passwort!', $user->fresh()->password));
    }

    /**
     * Reset the password with a token that is older than the 24 h expiry.
     *
     * Expects Password::INVALID_TOKEN and the old password to keep working.
     */
    #[Group('critical')]
    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Altes-sicheres-Passwort!']);
        $token = Password::createToken($user);
        $this->travel(25)->hours();

        $status = Password::reset(
            ['email' => $user->email, 'password' => 'Ein-neues-langes-Passwort!', 'password_confirmation' => 'Ein-neues-langes-Passwort!', 'token' => $token],
            fn (User $u, string $password) => $u->forceFill(['password' => $password])->save(),
        );
        $this->assertSame(Password::INVALID_TOKEN, $status);
        $this->assertTrue(Hash::check('Altes-sicheres-Passwort!', $user->fresh()->password));
    }
}
