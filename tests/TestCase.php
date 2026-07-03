<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stub the @vite Blade directive so tests that render a full page
        // don't require a built public/build/manifest.json. Without this,
        // CI (which doesn't build assets in the test job) throws
        // ViteManifestNotFoundException → 500 on every HTTP page render.
        // Placed in the base TestCase so it also covers class-based tests
        // that don't pick up Pest's beforeEach hook.
        $this->withoutVite();
    }
}
