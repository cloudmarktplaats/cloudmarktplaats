<?php

declare(strict_types=1);

use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserIdentity;

beforeEach(function (): void {
    // Anonymous requests default to Dutch via SetLocale (no session cookie
    // set), so fixtures use 'nl' to match the locale used during the
    // actual OAuth callback request.
    LegalDocument::factory()->tos()->create([
        'locale' => 'nl',
        'published_at' => now(),
    ]);
    LegalDocument::factory()->privacy()->create([
        'locale' => 'nl',
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

/**
 * GitHub hands back the user's *primary, verified* address (Socialite's
 * GithubProvider requests the user:email scope by default and reads it from
 * /user/emails). So an OAuth signup arrives pre-verified and must be able to
 * use the site immediately.
 *
 * It could not. OAuthController::callback() passes 'email_verified_at' => now()
 * to User::create(), but that column is absent from User::$fillable, so
 * mass-assignment dropped it silently — every OAuth account landed unverified.
 * And unlike the password flow, this one fires no Registered event, so no
 * verification mail ever went out either: those accounts could not place a
 * listing or send an invite, and had no way to fix it.
 *
 * Found on 2026-07-15 with 14 of 17 GitHub accounts stuck this way.
 */
it('marks an oauth signup as verified when the provider supplies an email', function (): void {
    fakeSocialiteUser('github', '4242', 'verified@example.nl', 'Ver');

    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    $user = User::where('email', 'verified@example.nl')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('leaves an oauth signup unverified when the provider supplies no email', function (): void {
    // No address means the placeholder uid@github.local — verifying that would
    // be a lie: nobody can receive mail there.
    fakeSocialiteUser('github', '4343', null, 'Anon');

    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    $user = User::where('email', '4343@github.local')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
});

it('lets a verified oauth user reach the listing wizard', function (): void {
    // The real consequence of the bug: `verified` middleware guards the wizard,
    // so an unverified account cannot place a listing at all.
    fakeSocialiteUser('github', '4444', 'wizard@example.nl', 'Wiz');
    $this->get('/oauth/github/callback?code=fake')->assertRedirect('/');

    $user = User::where('email', 'wizard@example.nl')->firstOrFail();

    $this->actingAs($user)->get('/listings/new')->assertOk();
});
