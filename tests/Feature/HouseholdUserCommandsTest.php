<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Console user administration: account discovery, credential recovery and role protection.
 */
class HouseholdUserCommandsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * List an administrator and a regular member.
     *
     * Expects IDs, names, e-mails and roles, without password hashes or remember tokens.
     */
    public function test_listing_shows_login_details_and_roles_without_credentials(): void
    {
        $admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'is_admin' => true]);
        $member = User::factory()->create(['name' => 'Member', 'email' => 'member@example.test', 'is_admin' => false]);

        $this->artisan('splitnest:users')
            ->expectsTable(['ID', 'Name', 'E-mail', 'Admin'], [
                [$admin->id, 'Admin', 'admin@example.test', 'Yes'],
                [$member->id, 'Member', 'member@example.test', 'No'],
            ])
            ->doesntExpectOutputToContain($admin->password)
            ->doesntExpectOutputToContain($admin->remember_token)
            ->assertSuccessful();
    }

    /**
     * List accounts using the administrators-only option.
     *
     * Expects the administrator to appear and the regular member to be omitted.
     */
    public function test_listing_can_be_limited_to_administrators(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->artisan('splitnest:users', ['--admins' => true])
            ->expectsOutputToContain($admin->email)
            ->doesntExpectOutputToContain($member->email)
            ->assertSuccessful();
    }

    /**
     * List accounts in a fresh installation.
     *
     * Expects a successful result with instructions for creating the first administrator.
     */
    public function test_empty_listing_explains_how_to_create_an_admin(): void
    {
        $this->artisan('splitnest:users')
            ->expectsOutput(__('users.list_command.empty'))
            ->assertSuccessful();
    }

    /**
     * Recover an administrator account by entering a new password twice.
     *
     * Expects a valid password hash, a rotated remember token and removed reset links, with admin access retained.
     */
    public function test_password_recovery_by_id_hashes_the_password_and_revokes_old_credentials(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Password::broker()->createToken($user);
        $rememberToken = $user->remember_token;
        $newPassword = 'Recovered-password-123!';

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--password' => true])
            ->expectsQuestion(__('users.command.password'), $newPassword)
            ->expectsQuestion(__('users.command.password_repeat'), $newPassword)
            ->doesntExpectOutputToContain($newPassword)
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue($user->is_admin);
    }

    /**
     * Find an account by e-mail and change its display name and address.
     *
     * Expects trimmed values, a lowercase e-mail, cleared verification and reset links, and an unchanged password.
     */
    public function test_editing_by_email_normalizes_the_address_and_preserves_the_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);
        $hash = $user->password;
        Password::broker()->createToken($user);

        $this->artisan('splitnest:user-edit', [
            'user' => ' OLD@EXAMPLE.TEST ', '--name' => ' New name ', '--email' => ' NEW@EXAMPLE.TEST ',
        ])->assertSuccessful();

        $user->refresh();
        $this->assertSame('New name', $user->name);
        $this->assertSame('new@example.test', $user->email);
        $this->assertSame($hash, $user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.test']);
    }

    /**
     * Edit an account interactively while retaining its defaults and leaving the password empty.
     *
     * Expects the existing password hash to remain unchanged.
     */
    public function test_interactive_edit_keeps_blank_password_and_existing_defaults(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $hash = $user->password;

        $this->artisan('splitnest:user-edit', ['user' => $user->id])
            ->expectsQuestion(__('users.command.name'), $user->name)
            ->expectsQuestion(__('users.command.email'), $user->email)
            ->expectsQuestion(__('users.edit_command.optional_password'), '')
            ->expectsConfirmation(__('users.edit_command.admin_question'), 'yes')
            ->assertSuccessful();

        $this->assertSame($hash, $user->fresh()->password);
    }

    /**
     * Grant and then remove admin access while another administrator remains.
     *
     * Expects both operations to succeed and persist the requested role.
     */
    public function test_admin_access_can_be_granted_and_removed_when_another_admin_exists(): void
    {
        User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--admin' => true])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--no-admin' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);
    }

    /**
     * Attempt to rename and demote the only administrator.
     *
     * Expects a failure explaining the restriction and neither change to be saved.
     */
    public function test_last_admin_cannot_be_demoted_and_other_edits_are_not_saved(): void
    {
        $user = User::factory()->create(['name' => 'Only admin', 'is_admin' => true]);

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--no-admin' => true, '--name' => 'Changed'])
            ->expectsOutput(__('users.edit_command.last_admin'))
            ->assertFailed();

        $this->assertTrue($user->fresh()->is_admin);
        $this->assertSame('Only admin', $user->fresh()->name);
    }

    /**
     * Provide invalid password and confirmation pairs for credential validation.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'too short' => ['short', 'short'],
            'confirmation differs' => ['A-long-password-123!', 'Another-password-123!'],
            'empty password' => ['', ''],
        ];
    }

    /**
     * Submit a short, empty or unconfirmed password together with a name change.
     *
     * Expects a failed command and the original password hash and name to remain unchanged.
     */
    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_does_not_change_the_account(string $password, string $confirmation): void
    {
        $user = User::factory()->create(['name' => 'Original']);
        $hash = $user->password;

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--password' => true, '--name' => 'Changed'])
            ->expectsQuestion(__('users.command.password'), $password)
            ->expectsQuestion(__('users.command.password_repeat'), $confirmation)
            ->assertFailed();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame('Original', $user->fresh()->name);
    }

    /**
     * Provide invalid account details and mutually exclusive role options.
     *
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function invalidOptions(): array
    {
        return [
            'duplicate email' => [['--email' => 'other@example.test']],
            'invalid email' => [['--email' => 'not-an-email']],
            'empty name' => [['--name' => ' ']],
            'conflicting roles' => [['--admin' => true, '--no-admin' => true]],
        ];
    }

    /**
     * Submit invalid account details or contradictory role options.
     *
     * Expects failure without changing the target account or creating another account.
     */
    #[DataProvider('invalidOptions')]
    public function test_invalid_options_leave_the_account_unchanged(array $options): void
    {
        User::factory()->create(['email' => 'other@example.test']);
        $user = User::factory()->create(['name' => 'Original', 'email' => 'original@example.test']);

        $this->artisan('splitnest:user-edit', ['user' => $user->id] + $options)->assertFailed();

        $this->assertSame('Original', $user->fresh()->name);
        $this->assertSame('original@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('users', 2);
    }

    /**
     * Try to grant admin access to an unknown e-mail address.
     *
     * Expects an account-not-found message, a failed command and no created user.
     */
    public function test_missing_account_fails_without_creating_a_user(): void
    {
        $this->artisan('splitnest:user-edit', ['user' => 'missing@example.test', '--admin' => true])
            ->expectsOutput(__('users.edit_command.not_found', ['user' => 'missing@example.test']))
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Request a password change with interaction disabled.
     *
     * Expects guidance to use an interactive terminal and an unchanged password.
     */
    public function test_password_recovery_requires_an_interactive_terminal(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--password' => true, '--no-interaction' => true])
            ->expectsOutput(__('users.edit_command.interaction_required'))
            ->assertFailed();

        $this->assertSame($hash, $user->fresh()->password);
    }

    /**
     * Start the interactive edit flow with interaction disabled.
     *
     * Expects a failed command explaining that an interactive terminal is required.
     */
    public function test_edit_without_options_requires_an_interactive_terminal(): void
    {
        $user = User::factory()->create();

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--no-interaction' => true])
            ->expectsOutput(__('users.edit_command.interaction_required'))
            ->assertFailed();
    }

    /**
     * Provide a display name explicitly with interaction disabled.
     *
     * Expects successful persistence without any prompt.
     */
    public function test_explicit_name_change_can_run_without_interaction(): void
    {
        $user = User::factory()->create();

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--name' => 'Updated', '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame('Updated', $user->fresh()->name);
    }

    /**
     * Complete the interactive prompts for an existing regular member.
     *
     * Expects the new name, e-mail, hashed password and administrator access to be saved.
     */
    public function test_interactive_edit_updates_account_details_password_and_admin_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('splitnest:user-edit', ['user' => $user->email])
            ->expectsQuestion(__('users.command.name'), 'Updated administrator')
            ->expectsQuestion(__('users.command.email'), 'updated@example.test')
            ->expectsQuestion(__('users.edit_command.optional_password'), 'New-password-123!')
            ->expectsQuestion(__('users.command.password_repeat'), 'New-password-123!')
            ->expectsConfirmation(__('users.edit_command.admin_question'), 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('Updated administrator', $user->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('New-password-123!', $user->password));
    }

    /**
     * Set a password at the configured twelve-character minimum.
     *
     * Expects a successful command and a hash matching the new password.
     */
    public function test_password_with_exactly_twelve_characters_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--password' => true])
            ->expectsQuestion(__('users.command.password'), 'Abcdef12345!')
            ->expectsQuestion(__('users.command.password_repeat'), 'Abcdef12345!')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Abcdef12345!', $user->fresh()->password));
    }

    /**
     * Combine a password change with demoting the last administrator.
     *
     * Expects failure while preserving the old password, remember token and valid reset link.
     */
    public function test_failed_demotion_preserves_password_remember_token_and_reset_link(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $hash = $user->password;
        $rememberToken = $user->remember_token;
        $resetToken = Password::broker()->createToken($user);

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--password' => true, '--no-admin' => true])
            ->expectsQuestion(__('users.command.password'), 'New-password-123!')
            ->expectsQuestion(__('users.command.password_repeat'), 'New-password-123!')
            ->expectsOutput(__('users.edit_command.last_admin'))
            ->assertFailed();

        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertTrue(Password::broker()->tokenExists($user, $resetToken));
    }

    /**
     * Change only the display name of an existing account.
     *
     * Expects e-mail verification, the remember token and the valid reset link to remain intact.
     */
    public function test_name_only_edit_preserves_email_verification_and_recovery_credentials(): void
    {
        $user = User::factory()->create();
        $verifiedAt = $user->email_verified_at->toDateTimeString();
        $rememberToken = $user->remember_token;
        $resetToken = Password::broker()->createToken($user);

        $this->artisan('splitnest:user-edit', ['user' => $user->id, '--name' => 'New name'])->assertSuccessful();

        $user->refresh();
        $this->assertSame($verifiedAt, $user->email_verified_at->toDateTimeString());
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertTrue(Password::broker()->tokenExists($user, $resetToken));
    }

    /**
     * Run administrator creation with an e-mail already assigned to a regular member.
     *
     * Expects failure with no additional user and no changes to the original name, password or role.
     */
    public function test_creating_an_admin_with_an_existing_email_does_not_overwrite_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Original', 'is_admin' => false]);
        $hash = $user->password;

        $this->artisan('splitnest:user', ['email' => $user->email, '--name' => 'Replacement'])
            ->expectsQuestion(__('users.command.password'), 'New-password-123!')
            ->expectsQuestion(__('users.command.password_repeat'), 'New-password-123!')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $user->refresh();
        $this->assertSame('Original', $user->name);
        $this->assertSame($hash, $user->password);
        $this->assertFalse($user->is_admin);
    }
}
