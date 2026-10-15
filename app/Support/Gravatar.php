<?php

namespace App\Support;

/**
 * Builds Gravatar avatar URLs from e-mail addresses.
 *
 * Returns null when Gravatar is disabled in the system settings or no e-mail
 * address is available, so callers can simply skip the image.
 */
final class Gravatar
{
    /**
     * Build the Gravatar URL for an e-mail address, or null when disabled.
     *
     * The fallback style (d parameter) is configurable in the settings.
     */
    public static function url(?string $email, int $size = 80): ?string
    {
        if (! Settings::gravatarEnabled() || blank($email)) {
            return null;
        }

        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($email)))."?s={$size}&d=".Settings::gravatarStyle();
    }
}
