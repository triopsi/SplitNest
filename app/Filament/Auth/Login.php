<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

/**
 * Login page with first-run behaviour: as long as no account exists (fresh
 * container setup), guests are forwarded to the first-user registration.
 */
class Login extends BaseLogin
{
    /**
     * Redirect to the first-user registration when the users table is empty.
     */
    public function mount(): void
    {
        parent::mount();

        if (self::needsFirstUser()) {
            $this->redirect(Filament::getRegistrationUrl());
        }
    }

    /**
     * Show the "register" link in the subheading only while the initial
     * setup is pending.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return self::needsFirstUser() ? parent::getSubheading() : null;
    }

    /**
     * Whether no user account exists yet (initial setup pending). Returns
     * false before the migrations have run, so error pages stay reachable.
     */
    public static function needsFirstUser(): bool
    {
        return Schema::hasTable('users') && User::count() === 0;
    }
}
