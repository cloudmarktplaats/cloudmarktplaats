<?php

use App\Models\Category;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds top-level categories from spec', function () {
    $this->seed(CategorySeeder::class);

    expect(Category::whereRaw("path = 'servers'")->exists())->toBeTrue();
    expect(Category::whereRaw("path = 'networking'")->exists())->toBeTrue();
    expect(Category::count())->toBeGreaterThanOrEqual(12);
});

it('seeds legal documents in nl + en', function () {
    $this->seed(LegalDocumentSeeder::class);

    expect(LegalDocument::current('tos', 'nl'))->not->toBeNull();
    expect(LegalDocument::current('privacy', 'nl'))->not->toBeNull();
});

it('creates demo admin and user', function () {
    $this->seed(DemoUserSeeder::class);

    expect(User::where('email', 'admin@example.local')->first()?->role)->toBe('admin');
    expect(User::where('email', 'user@example.local')->first()?->role)->toBe('user');
});

/*
 * De bundeling van 31-08-2026. Twee losse wijzigingen (het artikel over
 * particulier en zakelijk verkopen in de ToS, en het doel van de mailinglijst
 * in de privacyverklaring) horen in 1 versie te zitten, anders krijgt een lid
 * het acceptatiescherm twee keer kort achter elkaar voor twee losse dingen.
 *
 * Deze test bewaakt de koppeling tussen tekst en versie: bump je de versie
 * zonder de tekst, of ship je de tekst zonder de bump, dan valt hij om.
 */
it('bundles both legal changes into 1 version that asks members to accept again', function () {
    // De stand van vóór de bump: 4 documenten op 1.0.0, door het lid aanvaard.
    $user = User::factory()->create(['email_verified_at' => now()]);
    app()->setLocale('nl');

    foreach (['tos', 'privacy'] as $type) {
        foreach (['nl', 'en'] as $locale) {
            $doc = LegalDocument::query()->create([
                'type' => $type,
                'locale' => $locale,
                'version' => '1.0.0',
                'markdown_content' => 'oud',
                'published_at' => now()->subMonth(),
            ]);

            if ($locale === 'nl') {
                LegalAcceptance::query()->create([
                    'user_id' => $user->id,
                    'legal_document_id' => $doc->id,
                    'accepted_at' => now()->subMonth(),
                    'ip_hash' => str_repeat('a', 64),
                ]);
            }
        }
    }

    $this->seed(LegalDocumentSeeder::class);

    // 1 seederronde, allebei de documenten op dezelfde nieuwe versie.
    expect(LegalDocument::current('tos', 'nl')?->version)->toBe('1.1.0')
        ->and(LegalDocument::current('privacy', 'nl')?->version)->toBe('1.1.0');

    // En de tekst die de bump rechtvaardigt, zit er ook echt in.
    expect(LegalDocument::current('tos', 'nl')?->markdown_content)
        ->toContain('Particulier of zakelijk verkopen')
        ->toContain('geen keurmerk')
        ->and(LegalDocument::current('privacy', 'nl')?->markdown_content)
        ->toContain('Toestemming');

    // Het lid dat 1.0.0 aanvaardde, wordt 1 keer opnieuw gevraagd.
    $this->actingAs($user)->get('/listings/new')->assertRedirect('/legal/accept');
});
