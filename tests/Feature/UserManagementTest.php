<?php

namespace Tests\Feature;

use App\Filament\Auth\Profile;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * User administration: account creation, credential rules and the
 * self-protection rules for the own admin account.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin account.
     */
    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    /**
     * Create an admin and authenticate with the Filament admin panel active.
     */
    private function actingAsAdmin(): User
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * Create a user through the Filament create action.
     *
     * Expects the e-mail to be lowercased, the admin flag to be stored and
     * the password to be hashed, never plain.
     */
    #[Group('critical')]
    public function test_admin_can_create_user_with_hashed_password(): void
    {
        $this->actingAsAdmin();
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Daniel', 'email' => 'Daniel@Example.Test', 'password' => 'A-long-test-password!', 'is_admin' => true])
            ->assertHasNoActionErrors();
        $u = User::where('email', 'daniel@example.test')->firstOrFail();
        $this->assertTrue($u->is_admin);
        $this->assertTrue(Hash::check('A-long-test-password!', $u->password));
    }

    /**
     * Submit the create form with a duplicate e-mail and a too short password.
     *
     * Expects validation errors (unique e-mail, min 12 password) and no new
     * user record.
     */
    #[Group('critical')]
    public function test_duplicate_email_and_short_password_are_rejected(): void
    {
        $this->actingAsAdmin();
        $existing = User::factory()->create();
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Kopie', 'email' => $existing->email, 'password' => 'kurz'])
            ->assertHasActionErrors(['email' => 'unique', 'password' => 'min']);
        $this->assertDatabaseCount('users', 2);
    }

    /**
     * Edit a user while leaving the password field empty.
     *
     * Expects name/e-mail to be updated and the old password hash to remain
     * untouched.
     */
    public function test_editing_without_password_keeps_the_old_one(): void
    {
        $this->actingAsAdmin();
        $u = User::factory()->create(['password' => 'Original-password-1']);
        $oldHash = $u->password;
        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($u), data: ['name' => 'Umbenannt', 'email' => $u->email, 'password' => null, 'is_admin' => false])
            ->assertHasNoActionErrors();
        $this->assertSame('Umbenannt', $u->fresh()->name);
        $this->assertSame($oldHash, $u->fresh()->password);
        $this->assertTrue(Hash::check('Original-password-1', $u->fresh()->password));
    }

    /**
     * Try to delete or demote the own admin account.
     *
     * Expects the delete action to be hidden and the admin toggle disabled —
     * the own account must keep its admin rights.
     */
    #[Group('critical')]
    public function test_admin_cannot_delete_or_demote_own_account(): void
    {
        $admin = $this->actingAsAdmin();
        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($admin))
            ->mountAction(TestAction::make('edit')->table($admin))
            ->assertFormFieldIsDisabled('is_admin');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
    }

    /**
     * Open user management and the standalone profile as an admin.
     *
     * Expects user management in the panel sidebar and the profile dropdown
     * to link to the reachable standalone profile.
     */
    public function test_users_link_is_in_sidebar_and_profile_menu_links_to_profile(): void
    {
        $this->actingAsAdmin();

        $this->get(UserResource::getUrl())
            ->assertOk()
            ->assertSee('href="'.UserResource::getUrl().'"', false);

        $this->get(Profile::getUrl())->assertOk();

        $items = Filament::getPanel('admin')->getUserMenuItems();
        $this->assertArrayNotHasKey('users', $items);
        $this->assertArrayHasKey('profile', $items);
        $this->assertSame(Profile::getUrl(), $items['profile']->getUrl());
        $this->assertSame('Edit profile', $items['profile']->getLabel());
    }

    /**
     * Check panel access for an admin and a regular user.
     *
     * Expects only the admin to pass canAccessPanel().
     */
    #[Group('critical')]
    public function test_only_admins_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertTrue($this->admin()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
    }
}
