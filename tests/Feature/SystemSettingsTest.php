<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettings;
use App\Models\Participant;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Toggle the Gravatar setting off and on via the settings page.
     *
     * Expects avatars to be hidden by default (no gravatar.com URLs anywhere)
     * and to appear for the user menu and participants once enabled.
     */
    public function test_gravatar_toggle_controls_avatars_everywhere(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        Participant::create(['name' => 'Daniel', 'email' => 'daniel@example.test']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Gravatar is disabled by default: no avatars anywhere.
        $this->assertFalse(Settings::gravatarEnabled());
        $this->assertNull($admin->getFilamentAvatarUrl());
        $this->get('/admin?month=2027-01')->assertDontSee('gravatar.com/avatar/');

        // Enable via the settings page.
        Livewire::test(SystemSettings::class)
            ->assertSet('data.gravatar_enabled', false)
            ->set('data.gravatar_enabled', true)
            ->call('save');
        $this->assertTrue(Settings::gravatarEnabled());

        // User menu avatar and participant avatars appear.
        $this->assertSame('https://www.gravatar.com/avatar/'.md5($admin->email).'?s=80&d=initials', $admin->getFilamentAvatarUrl());
        $this->get('/admin?month=2027-01')->assertSee('gravatar.com/avatar/'.md5('daniel@example.test'), false);
    }
}
