<?php

declare(strict_types=1);

use App\Livewire\Auth\LegalAccept;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    // Tests use the app's default locale (en) so the LegalDocument::current
    // lookup in {@see App\Http\Middleware\LegalAcceptance} matches the
    // factory-created rows below.
    $this->locale = app()->getLocale();
});

it('redirects a user with stale acceptance to /legal/accept when hitting a legal-guarded route', function () {
    $oldTos = LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => $this->locale,
        'version' => '1.0.0',
        'published_at' => now()->subMonth(),
    ]);
    $oldPrivacy = LegalDocument::factory()->create([
        'type' => 'privacy',
        'locale' => $this->locale,
        'version' => '1.0.0',
        'published_at' => now()->subMonth(),
    ]);
    LegalAcceptance::create([
        'user_id' => $this->user->id,
        'legal_document_id' => $oldTos->id,
        'accepted_at' => now()->subMonth(),
        'ip_hash' => str_repeat('a', 64),
    ]);
    LegalAcceptance::create([
        'user_id' => $this->user->id,
        'legal_document_id' => $oldPrivacy->id,
        'accepted_at' => now()->subMonth(),
        'ip_hash' => str_repeat('a', 64),
    ]);

    // Publish a newer ToS version; the existing acceptance row is now stale.
    LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => $this->locale,
        'version' => '1.1.0',
        'published_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get('/listings/new')
        ->assertRedirect('/legal/accept');
});

it('lets through a user who has accepted every current version', function () {
    $tos = LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => $this->locale,
        'version' => '1.0.0',
        'published_at' => now()->subDay(),
    ]);
    $privacy = LegalDocument::factory()->create([
        'type' => 'privacy',
        'locale' => $this->locale,
        'version' => '1.0.0',
        'published_at' => now()->subDay(),
    ]);
    foreach ([$tos, $privacy] as $doc) {
        LegalAcceptance::create([
            'user_id' => $this->user->id,
            'legal_document_id' => $doc->id,
            'accepted_at' => now(),
            'ip_hash' => str_repeat('a', 64),
        ]);
    }

    // Hitting the legal-guarded wizard route should NOT redirect to
    // /legal/accept. The middleware should pass through to the
    // downstream wizard component; we only assert the negative case
    // (we don't render Livewire in this HTTP-level check).
    $response = $this->actingAs($this->user)->get('/listings/new');
    expect($response->headers->get('Location'))->not->toBe(url('/legal/accept'));
});

it('writes a LegalAcceptance row per outstanding doc and redirects home on accept', function () {
    LegalDocument::factory()->create([
        'type' => 'tos',
        'locale' => $this->locale,
        'version' => '2.0.0',
        'published_at' => now()->subHour(),
    ]);
    LegalDocument::factory()->create([
        'type' => 'privacy',
        'locale' => $this->locale,
        'version' => '2.0.0',
        'published_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user);

    Livewire::test(LegalAccept::class)
        ->call('accept')
        ->assertRedirect('/');

    expect(LegalAcceptance::query()->where('user_id', $this->user->id)->count())->toBe(2);
});

it('redirects /legal/accept to / when no outstanding documents remain', function () {
    // No current legal docs published at all — nothing to accept.
    $this->actingAs($this->user);

    Livewire::test(LegalAccept::class)->assertRedirect('/');
});
