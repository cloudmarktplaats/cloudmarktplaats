<?php

declare(strict_types=1);

use App\Models\LegalDocument;

it('renders the current published tos as html', function () {
    LegalDocument::create([
        'type' => 'tos',
        'locale' => 'nl',
        'version' => '1.0.0',
        'markdown_content' => "# Gebruiksvoorwaarden\n\nWij zijn een **bemiddelaar**.",
        'published_at' => now(),
    ]);

    $this->get('/legal/tos')
        ->assertOk()
        ->assertSee('Gebruiksvoorwaarden')
        ->assertSee('<strong>bemiddelaar</strong>', false);
});

it('serves the english version via ?lang=en', function () {
    LegalDocument::create([
        'type' => 'privacy', 'locale' => 'nl', 'version' => '1.0.0',
        'markdown_content' => '# Privacyverklaring', 'published_at' => now(),
    ]);
    LegalDocument::create([
        'type' => 'privacy', 'locale' => 'en', 'version' => '1.0.0',
        'markdown_content' => '# Privacy Policy', 'published_at' => now(),
    ]);

    $this->get('/legal/privacy?lang=en')->assertOk()->assertSee('Privacy Policy');
});

it('falls back to nl when the requested locale has no document', function () {
    LegalDocument::create([
        'type' => 'tos', 'locale' => 'nl', 'version' => '1.0.0',
        'markdown_content' => '# Gebruiksvoorwaarden', 'published_at' => now(),
    ]);

    $this->get('/legal/tos?lang=en')->assertOk()->assertSee('Gebruiksvoorwaarden');
});

it('escapes raw html in the markdown source (no injection)', function () {
    LegalDocument::create([
        'type' => 'tos', 'locale' => 'nl', 'version' => '1.0.0',
        'markdown_content' => "# Titel\n\n<script>alert(1)</script>",
        'published_at' => now(),
    ]);

    $response = $this->get('/legal/tos')->assertOk();
    expect($response->getContent())->not->toContain('<script>alert(1)</script>');
});

it('404s an unknown legal type', function () {
    $this->get('/legal/cookies')->assertNotFound();
});

it('404s when no document is published yet', function () {
    $this->get('/legal/tos')->assertNotFound();
});
