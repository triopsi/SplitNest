<?php

namespace Tests\Feature;

use App\Filament\Auth\InvitationRegister;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Mail\HouseholdInvitationMail;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Services\InviteToHousehold;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Household invitations: admins invite by e-mail, invited members register
 * themselves with a one-time token link (default 24 h valid).
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin and authenticate with the Filament admin panel active.
     */
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * Capture the plain token from the mailed invitation link.
     */
    private function mailedToken(): string
    {
        Mail::assertSent(HouseholdInvitationMail::class);
        /** @var HouseholdInvitationMail $mail */
        $mail = Mail::sent(HouseholdInvitationMail::class)->first();

        return $mail->token;
    }

    /**
     * Invite a new member through the invite action in the user management.
     *
     * Expects a pending invitation with a lowercased e-mail, the stored token
     * to be the SHA-256 hash of the mailed plain token (never the plain
     * token itself) and the mail to be sent.
     */
    #[Group('critical')]
    public function test_admin_can_invite_by_email_and_the_token_is_stored_hashed(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        Livewire::test(ManageUsers::class)
            ->callAction('invite', data: ['email' => 'Neu@Example.Test'])
            ->assertHasNoActionErrors();

        $invitation = HouseholdInvitation::firstOrFail();
        $this->assertSame('neu@example.test', $invitation->email);
        $this->assertTrue($invitation->isPending());
        $this->assertSame(HouseholdInvitation::hashToken($this->mailedToken()), $invitation->token);
    }

    /**
     * Invite an e-mail address that already belongs to an account.
     *
     * Expects no invitation row and no mail to be sent.
     */
    public function test_inviting_an_address_that_already_has_an_account_is_rejected(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $existing = User::factory()->create();
        Livewire::test(ManageUsers::class)
            ->callAction('invite', data: ['email' => $existing->email]);
        $this->assertDatabaseCount('household_invitations', 0);
        Mail::assertNothingSent();
    }

    /**
     * Invite the same address twice.
     *
     * Expects the second invite to replace the first one: exactly one row
     * remains, with a fresh token (a re-invite works as a resend).
     */
    public function test_reinviting_replaces_the_pending_invitation(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $first = app(InviteToHousehold::class)->invite('neu@example.test', $admin->id);
        app(InviteToHousehold::class)->invite('neu@example.test', $admin->id);
        $this->assertDatabaseCount('household_invitations', 1);
        $this->assertNotSame($first->token, HouseholdInvitation::firstOrFail()->token);
    }

    /**
     * Invite with the default config and with a 72 h override.
     *
     * Expects the expiry to default to 24 hours and to follow the
     * `app.invitation_ttl_hours` config value.
     */
    public function test_invitation_ttl_defaults_to_24_hours_and_is_configurable(): void
    {
        Mail::fake();
        $this->freezeTime();
        $admin = $this->actingAsAdmin();
        $invitation = app(InviteToHousehold::class)->invite('neu@example.test', $admin->id);
        $this->assertSame(24, (int) config('app.invitation_ttl_hours'));
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $invitation->expires_at->toDateTimeString());

        config(['app.invitation_ttl_hours' => 72]);
        $again = app(InviteToHousehold::class)->invite('zwei@example.test', $admin->id);
        $this->assertSame(now()->addHours(72)->toDateTimeString(), $again->expires_at->toDateTimeString());
    }

    /**
     * Try to invite a malformed e-mail address via the service.
     *
     * Expects a ValidationException before any row is created.
     */
    public function test_invite_rejects_an_invalid_email_address(): void
    {
        $admin = $this->actingAsAdmin();
        $this->expectException(ValidationException::class);
        app(InviteToHousehold::class)->invite('keine-mail', $admin->id);
    }

    /**
     * Register through a valid invitation link.
     *
     * Expects the page to render, the invited e-mail to be prefilled, the new
     * account to become an admin with a hashed password, the invitation to be
     * marked as accepted and the member to be logged in.
     */
    #[Group('critical')]
    public function test_invited_member_can_register_and_becomes_admin(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $invitation = app(InviteToHousehold::class)->invite('neu@example.test', $admin->id);
        $token = $this->mailedToken();
        Auth::logout();

        $this->get('/admin/einladung/'.$token)->assertOk();
        Livewire::test(InvitationRegister::class, ['token' => $token])
            ->set('data.name', 'Neues Mitglied')
            ->set('data.password', 'A-long-test-password!')
            ->set('data.passwordConfirmation', 'A-long-test-password!')
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'neu@example.test')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('A-long-test-password!', $user->password));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Open the registration page with expired, already-used and unknown tokens.
     *
     * Expects a redirect to the login page in all cases and no user accounts
     * to be created.
     */
    #[Group('critical')]
    public function test_expired_invalid_or_used_tokens_cannot_register(): void
    {
        foreach (['expired', 'accepted'] as $state) {
            $plain = HouseholdInvitation::generateToken();
            HouseholdInvitation::factory()->$state()->create(['token' => HouseholdInvitation::hashToken($plain)]);
            $this->get('/admin/einladung/'.$plain)->assertRedirect('/admin/login');
        }
        $this->get('/admin/einladung/'.HouseholdInvitation::generateToken())->assertRedirect('/admin/login');
        $this->assertDatabaseCount('users', 0);
    }
}
