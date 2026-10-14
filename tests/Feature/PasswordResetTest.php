<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_pages_are_reachable_and_linked_from_login(): void
    {
        $this->get('/admin/password-reset/request')->assertOk();
        $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request');
    }

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
}
