<?php

declare(strict_types=1);

use App\Services\Auth\OAuthProviderRegistry;

/**
 * Hard guard — keep the codebase free of Big Tech OAuth providers.
 *
 * Two layers of protection:
 *   1) The registry must refuse the excluded provider names.
 *   2) The actual source files for the OAuth controller + registry must
 *      not contain those names at all (case-insensitive substring match).
 *      The check looks at the file *bodies*; any reference — even in a
 *      comment — would fail the test. That is intentional.
 */
it('rejects google, facebook and twitter at the registry', function (): void {
    expect(OAuthProviderRegistry::isAllowed('google'))->toBeFalse();
    expect(OAuthProviderRegistry::isAllowed('facebook'))->toBeFalse();
    expect(OAuthProviderRegistry::isAllowed('twitter'))->toBeFalse();
});

it('contains no google or facebook strings in oauth source files', function (): void {
    // Resolve paths via __DIR__ so this test does not depend on the
    // Laravel application being booted (it sits in the Unit suite).
    $appRoot = dirname(__DIR__, 3).'/app';

    $sources = file_get_contents($appRoot.'/Http/Controllers/Auth/OAuthController.php')
        .file_get_contents($appRoot.'/Services/Auth/OAuthProviderRegistry.php');

    $haystack = strtolower($sources);

    expect(str_contains($haystack, 'google'))->toBeFalse();
    expect(str_contains($haystack, 'facebook'))->toBeFalse();
});
