<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\Web3Controller;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Listings\ReportController;
use App\Http\Controllers\Listings\SearchController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\LegalAccept;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\SiweOnboarding;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Livewire\Homelab\Feed as HomelabFeed;
use App\Livewire\Listings\Browse as ListingsBrowse;
use App\Livewire\Listings\Detail as ListingDetail;
use App\Livewire\Listings\Wizard as ListingWizard;
use App\Livewire\Profile\Deals as ProfileDeals;
use App\Livewire\Profile\Invites as ProfileInvites;
use App\Livewire\Profile\Security as ProfileSecurity;
use App\Livewire\Profile\Stats as ProfileStats;
use App\Livewire\Profile\TwoFactorSetup;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

// Homepage: marketing landing for guests, listings grid for authenticated users.
Route::get('/', function () {
    return auth()->check()
        ? redirect('/listings')
        : view('pages.home');
})->name('home');

// Static marketing pages.
Route::view('/over-ons', 'pages.about')->name('about');
Route::view('/waarden', 'pages.values')->name('values');
Route::view('/faq', 'pages.faq')->name('faq');
Route::view('/sponsors', 'pages.sponsor')->name('sponsor');
Route::view('/roadmap', 'pages.roadmap')->name('roadmap');

// Homelab-showcase: publieke feed, posten vereist login (flag-gated in mount()).
Route::get('/homelabs', HomelabFeed::class)->name('homelabs');

Route::get('/healthz', HealthController::class);

Route::get('/register', Register::class)->middleware('guest')->name('register');

Route::get('/email/verify-notice', VerifyEmailNotice::class)
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::post('/email/verification-notification', function () {
    auth()->user()?->sendEmailVerificationNotification();

    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/login', Login::class)->middleware('guest')->name('login');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::get('/forgot-password', ForgotPassword::class)->middleware('guest')->name('password.request');

Route::get('/reset-password/{token}', ResetPassword::class)->middleware('guest')->name('password.reset');

// OAuth — provider-agnostic. Allowed providers are whitelisted in
// App\Services\Auth\OAuthProviderRegistry; unknown providers 404.
Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect']);
Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback']);

// SIWE (Sign-In With Ethereum) — JSON endpoints driven by the wallet
// adapter on the front-end. /verify is CSRF-exempt (see bootstrap/app.php)
// because the wallet adapter has no session cookie; replay is blocked by
// the single-use nonce stored in auth_nonces.
Route::get('/auth/web3/nonce', [Web3Controller::class, 'nonce']);
Route::post('/auth/web3/verify', [Web3Controller::class, 'verify']);
Route::get('/auth/web3/onboarding/{address}', SiweOnboarding::class)
    ->where('address', '0x[a-fA-F0-9]{40}')
    ->middleware('guest')
    ->name('siwe.onboarding');

// Profile / security
Route::get('/profile/security', ProfileSecurity::class)
    ->middleware('auth')
    ->name('profile.security');

Route::get('/profile/security/2fa', TwoFactorSetup::class)
    ->middleware('auth')
    ->name('profile.security.2fa');

// Invites are gamification: only verified members earn credits, so the
// page (and its flag check in mount()) requires both auth and verified,
// matching the listings-wizard gate rather than the plain 'auth' used by
// the security pages above.
Route::get('/profile/invites', ProfileInvites::class)
    ->middleware(['auth', 'verified'])
    ->name('profile.invites');

Route::get('/profile/stats', ProfileStats::class)
    ->middleware('auth')
    ->name('profile.stats');

Route::get('/profile/deals', ProfileDeals::class)
    ->middleware('auth')
    ->name('profile.deals');

// 2FA challenge after primary auth — guest-accessible because the user
// is not yet seated in the session at this point.
Route::get('/2fa/challenge', TwoFactorChallenge::class)
    ->middleware('guest')
    ->name('2fa.challenge');

// Legal re-acceptance page. Mounted under `auth` only — applying the
// `legal` middleware here would create a redirect loop with itself.
Route::get('/legal/accept', LegalAccept::class)
    ->middleware('auth')
    ->name('legal.accept');

// Public, versioned legal documents. `{type}` is whitelisted to
// tos|privacy inside the controller; `?lang=en` switches locale.
Route::get('/legal/{type}', [LegalController::class, 'show'])
    ->where('type', 'tos|privacy')
    ->name('legal.show');

// Listing wizard — auth + verified + legal guard. Drafts are persisted
// after every step so users can resume via /listings/{ulid}/edit. The
// `legal` middleware re-prompts ToS / privacy acceptance whenever a new
// version has been published since the user last agreed (creating a
// listing is a legally consequential action).
Route::get('/listings/new', ListingWizard::class)
    ->middleware(['auth', 'verified', 'legal'])
    ->name('listings.create');

Route::get('/listings/{listing:ulid}/edit', ListingWizard::class)
    ->middleware(['auth', 'verified', 'legal'])
    ->where('listing', '[0-9A-HJKMNP-TV-Z]{26}')
    ->name('listings.edit');

// Public browse + detail. Anonymous browsing is enabled by feature flag
// `anonymous_browse`. Category routing uses an ltree path so all
// descendants of the prefix are included automatically.
Route::get('/listings', ListingsBrowse::class)->name('listings.index');

Route::get('/c/{categoryPath}', ListingsBrowse::class)
    ->where('categoryPath', '[a-z0-9._-]+')
    ->name('listings.category');

Route::get('/listings/{ulid}-{slug}', ListingDetail::class)
    ->where('ulid', '[0-9A-HJKMNP-TV-Z]{26}')
    ->where('slug', '[a-z0-9-]+')
    ->name('listings.detail');

// Full-text search — backed by the SearchInterface contract so the
// Postgres implementation can be swapped for Meilisearch later.
Route::get('/search', SearchController::class)->name('listings.search');

// Reports — auth-only. Polymorphic store endpoint; Foundation only
// wires listings, more reportable types attach in later phases.
Route::post('/reports/listing/{listing}', [ReportController::class, 'storeForListing'])
    ->middleware('auth')
    ->name('reports.listing.store');

Route::post('/reports/homelab/{post:ulid}', [ReportController::class, 'storeForHomelabPost'])
    ->middleware('auth')
    ->name('reports.homelab.store');
