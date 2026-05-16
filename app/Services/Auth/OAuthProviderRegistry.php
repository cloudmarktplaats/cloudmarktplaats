<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Hardcoded whitelist of OAuth providers cloudmarktplaats supports.
 *
 * Only privacy-respecting providers that fit our values (open-source-friendly,
 * developer-centric) are allowed. The list intentionally excludes Big Tech
 * identity providers; see tests/Unit/Auth/ProviderExclusionTest.php for the
 * guarding check.
 */
final class OAuthProviderRegistry
{
    /** @var list<string> */
    private const ALLOWED = ['github', 'gitlab'];

    public static function isAllowed(string $provider): bool
    {
        if (! in_array($provider, self::ALLOWED, true)) {
            return false;
        }

        return (bool) config('cloudmarktplaats.features.oauth_'.$provider);
    }

    public static function identityProvider(string $provider): string
    {
        return 'oauth_'.$provider;
    }

    /** @return list<string> */
    public static function allowed(): array
    {
        return self::ALLOWED;
    }
}
