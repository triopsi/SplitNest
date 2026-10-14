<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

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

    public function test_duplicate_email_and_short_password_are_rejected(): void
    {
        $this->actingAsAdmin();
        $existing = User::factory()->create();
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Kopie', 'email' => $existing->email, 'password' => 'kurz'])
            ->assertHasActionErrors(['email' => 'unique', 'password' => 'min']);
        $this->assertDatabaseCount('users', 2);
    }

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

    public function test_admin_cannot_delete_or_demote_own_account(): void
    {
        $admin = $this->actingAsAdmin();
        Livewire::test(ManageUsers::class)
            ->assertActionHidden(TestAction::make('delete')->table($admin))
            ->mountAction(TestAction::make('edit')->table($admin))
            ->assertFormFieldIsDisabled('is_admin');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
    }

    public function test_profile_dropdown_shows_users_link_for_authorized_admins(): void
    {
        $this->actingAsAdmin();
        $items = Filament::getPanel('admin')->getUserMenuItems();
        $this->assertArrayHasKey('users', $items);
        $this->assertSame(UserResource::getUrl(), $items['users']->getUrl());
    }

    public function test_only_admins_can_access_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertTrue($this->admin()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
    }
}
