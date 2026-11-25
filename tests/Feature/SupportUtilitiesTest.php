<?php

namespace Tests\Feature;

use App\Support\Gravatar;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regression coverage for small settings-backed application helpers. */
class SupportUtilitiesTest extends TestCase
{
    use RefreshDatabase;

    /** Verify Gravatar URLs honor enablement, normalized e-mail addresses, size, and fallback style. */
    public function test_gravatar_url_handles_disabled_blank_and_normalized_addresses(): void
    {
        $this->assertNull(Gravatar::url('person@example.test'));

        Settings::set('gravatar_enabled', '1');
        Settings::set('gravatar_style', 'monsterid');

        $this->assertNull(Gravatar::url('   '));
        $this->assertSame(
            'https://www.gravatar.com/avatar/'.md5('person@example.test').'?s=128&d=monsterid',
            Gravatar::url('  Person@Example.Test  ', 128),
        );
    }

    /** Verify setting reads return defaults until a value is saved and mail templates remain configurable. */
    public function test_settings_return_defaults_and_persist_custom_values(): void
    {
        $this->assertSame('fallback', Settings::get('missing_setting', 'fallback'));
        $this->assertFalse(Settings::gravatarEnabled());
        $this->assertSame('initials', Settings::gravatarStyle());
        $this->assertSame(__('mail.default_body'), Settings::mailBody());

        Settings::set('mail_body', 'Hello {name}');
        Settings::set('gravatar_enabled', '1');

        $this->assertSame('Hello {name}', Settings::mailBody());
        $this->assertTrue(Settings::gravatarEnabled());
    }
}
