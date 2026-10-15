<?php

namespace App\Support;

/**
 * Available UI locales, discovered from the lang/ directories.
 */
final class Locales
{
    /** Native display names for known locales (code => name). */
    private const NAMES = [
        'de' => 'Deutsch',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'pt' => 'Português',
    ];

    /**
     * Available UI locales (code => native name), discovered from lang/.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        $locales = [];
        foreach (glob(base_path('lang/*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $code = basename($dir);
            $locales[$code] = self::NAMES[$code] ?? $code;
        }

        return $locales ?: ['en' => 'English'];
    }

    /**
     * Whether the given code is an available locale.
     */
    public static function isAvailable(string $locale): bool
    {
        return array_key_exists($locale, self::available());
    }
}
