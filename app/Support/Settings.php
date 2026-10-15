<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tiny key-value store for system settings, backed by the settings table.
 */
final class Settings
{
    /**
     * Read a setting value, falling back to the given default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Persist a setting value.
     */
    public static function set(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    /**
     * Whether Gravatar avatars are shown for users and participants.
     */
    public static function gravatarEnabled(): bool
    {
        return self::get('gravatar_enabled', '0') === '1';
    }

    /**
     * The configurable closing e-mail body template (default: translated).
     */
    public static function mailBody(): string
    {
        return self::get('mail_body') ?? __('mail.default_body');
    }
}
