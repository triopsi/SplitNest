<?php

namespace App\Filament\Auth;

use App\Models\HouseholdInvitation;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Token-based registration page for invited household members.
 *
 * Reachable only via a valid invitation link. Creates an admin account for
 * the invited e-mail address (only admins can access the panel) and marks
 * the invitation as accepted. Registered manually as a panel route, so it
 * stays out of the page discovery and the navigation.
 */
class InvitationRegister extends Register
{
    /** Plain invitation token from the registration link (filled from the route). */
    #[Locked]
    public string $token = '';

    /**
     * Load the invitation for the route token; invalid or expired links are
     * sent to the login page with an error notification.
     */
    public function mount(): void
    {
        parent::mount();

        $invitation = $this->pendingInvitation();
        if (! $invitation) {
            Notification::make()->danger()->title('Diese Einladung ist ungültig, abgelaufen oder wurde bereits verwendet.')->persistent()->send();
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        $this->form->fill(['email' => $invitation->email]);
    }

    /**
     * Page title shown in the browser tab.
     */
    public function getTitle(): string|Htmlable
    {
        return 'Einladung annehmen';
    }

    /**
     * Heading above the registration form.
     */
    public function getHeading(): string|Htmlable|null
    {
        return 'Konto für den Haushalt anlegen';
    }

    /**
     * The invited address is fixed and shown read-only.
     */
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->readOnly()
            ->helperText('Die Einladung gilt für diese E-Mail-Adresse.');
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
     * Create the invited admin account and consume the invitation.
     *
     * The invitation is re-validated here (not only in mount), so a link
     * that expired or was used in the meantime cannot create an account.
     * E-mail and admin flag always come from the invitation, never from
     * client-provided form state.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When the invitation is no longer pending.
     */
    protected function handleRegistration(#[\SensitiveParameter] array $data): Model
    {
        $invitation = $this->pendingInvitation();
        if (! $invitation) {
            throw ValidationException::withMessages(['email' => 'Diese Einladung ist ungültig, abgelaufen oder wurde bereits verwendet.']);
        }

        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => $invitation->email,
            'password' => $data['password'],
            'is_admin' => true,
        ]);
        $invitation->update(['accepted_at' => now()]);

        return $user;
    }

    /**
     * Resolve the pending invitation for the current token, if any.
     */
    private function pendingInvitation(): ?HouseholdInvitation
    {
        return $this->token !== '' ? HouseholdInvitation::findPendingByToken($this->token) : null;
    }
}
