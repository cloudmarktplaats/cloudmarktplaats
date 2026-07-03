<?php

declare(strict_types=1);

use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;

beforeEach(function (): void {
    LegalDocument::factory()->tos()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
    LegalDocument::factory()->privacy()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
});

it('creates a new user from a github oauth callback', function (): void {
    fakeSocialiteUser('github', '999', 'gh@example.nl', 'Ghost');

    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    $user = User::where('email', 'gh@example.nl')->first();
    expect($user)->not->toBeNull();
    expect($user->identities()
        ->where('provider', 'oauth_github')
        ->where('provider_uid', '999')
        ->exists()
    )->toBeTrue();
    expect($user->legalAcceptances()->count())->toBe(2);
    expect(auth()->id())->toBe($user->id);
    expect($user->invite_credits)->toBe(3);
});

it('logs in an existing user matched by github identity', function (): void {
    $existing = User::factory()->create();
    UserIdentity::factory()->github('888')->for($existing)->create();

    fakeSocialiteUser('github', '888', 'irrelevant@example.nl');

    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    expect(auth()->id())->toBe($existing->id);
    expect(UserIdentity::where('provider', 'oauth_github')
        ->where('provider_uid', '888')
        ->whereNotNull('last_used_at')
        ->exists()
    )->toBeTrue();
});

it('refuses to silently merge when email matches but no identity exists', function (): void {
    User::factory()->create(['email' => 'taken@b.nl']);

    fakeSocialiteUser('github', '777', 'taken@b.nl');

    $this->get('/oauth/github/callback?code=fake')
        ->assertRedirect('/login')
        ->assertSessionHasErrors();

    expect(auth()->check())->toBeFalse();
    expect(UserIdentity::where('provider', 'oauth_github')
        ->where('provider_uid', '777')
        ->exists()
    )->toBeFalse();
});

it('rejects an unknown oauth provider on the redirect endpoint', function (): void {
    $this->get('/oauth/google/redirect')->assertStatus(404);
});
