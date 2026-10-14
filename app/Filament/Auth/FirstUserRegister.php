<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Initial-setup registration: creates the very first administrator account.
 *
 * Available only while the users table is empty; once any account exists the
 * page redirects to the login. Further accounts are created by admins in the
 * user management or via e-mail invitations.
 */
class FirstUserRegister extends Register
{
    /**
     * Block the page once an account exists: redirect to the login.
     */
    public function mount(): void
    {
        parent::mount();

        if (! Login::needsFirstUser()) {
            Notification::make()->title('Die Ersteinrichtung ist bereits abgeschlossen.')->send();
            $this->redirect(Filament::getLoginUrl());
        }
    }

    /**
     * Page title shown in the browser tab.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Ersteinrichtung';
    }

    /**
     * Heading above the registration form.
     */
    public function getHeading(): string|Htmlable|null
    {
        return 'Ersten Administrator anlegen';
    }

    /**
     * Household convention: passwords need at least 12 characters.
     */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rules(['min:12'])
            ->helperText('Mindestens 12 Zeichen.');
    }

    /**
     * Create the first account as administrator.
     *
     * The empty-users check runs again here (inside the registration
     * transaction), so a second "first" account cannot be created through a
     * replayed or concurrent request.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When an account already exists.
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        if (! Login::needsFirstUser()) {
            throw ValidationException::withMessages(['email' => 'Die Ersteinrichtung ist bereits abgeschlossen.']);
        }

        return $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
            'is_admin' => true,
        ]);
    }
}
