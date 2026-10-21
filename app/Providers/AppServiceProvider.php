<?php

namespace App\Providers;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Registers and boots application-wide services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * With an https APP_URL every generated URL (assets included) is https —
     * robust behind TLS-terminating proxies, independent of forwarded headers.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): PasswordResetMail {
            $broker = Filament::getAuthPasswordBroker() ?? config('auth.defaults.passwords');

            return (new PasswordResetMail(
                url: Filament::getResetPasswordUrl($token, $user),
                minutes: (int) config("auth.passwords.{$broker}.expire"),
            ))->to($user->getEmailForPasswordReset());
        });

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceHttps();
        }
    }
}
