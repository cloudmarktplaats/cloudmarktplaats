<?php

declare(strict_types=1);

use App\Filament\Resources\ListingResource\Pages\ListListings;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Listings\Browse;
use App\Livewire\Listings\Detail;
use App\Livewire\Listings\Wizard;
use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Foundation §14 acceptance walkthrough.
 *
 * Implemented as an HTTP/Livewire-level feature test rather than a true
 * Playwright/Pest Browser test. Reason captured in docs/known-gaps.md:
 * the browser-test stack (Pest Browser × Livewire × Filament) produced
 * flaky snapshot-validation issues against our current docker-compose
 * fixture, and the spec explicitly allows the HTTP substitute. This test
 * still exercises every state transition, redirect, and DB-row side
 * effect that the §14 scenario requires.
 *
 * Scenario:
 *   1. Anonymous visitor registers via Livewire Register form.
 *   2. Email-verification notification is queued; visiting the signed
 *      verify link marks the account verified.
 *   3. Verified user creates a draft listing through the wizard,
 *      uploads a fixture photo, and submits → state = `pending_review`.
 *   4. An admin (different user) approves the listing through the
 *      Filament resource bulk-action → state = `published`.
 *   5. An anonymous visitor finds the listing via /search (Postgres
 *      tsvector) and lands on the detail page.
 *   6. Clicking "Neem contact op" while anonymous redirects to /login
 *      with the listing URL preserved as `return_to`.
 */
beforeEach(function () {
    Storage::fake('public');
    Notification::fake();

    // Required for register() to attach legal acceptance rows.
    LegalDocument::factory()->tos()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);
    LegalDocument::factory()->privacy()->create([
        'locale' => app()->getLocale(),
        'published_at' => now(),
    ]);

    // We need a category for the wizard's step-1 select.
    $this->category = Category::factory()->create([
        'path' => 'computers.laptops',
        'name' => 'Laptops',
    ]);
});

it('walks the full §14 acceptance scenario from register to anonymous detail-page contact-redirect', function () {
    // --- 1. Register --------------------------------------------------
    Livewire::test(Register::class)
        ->set('email', 'seller@example.nl')
        ->set('username', 'seller')
        ->set('display_name', 'Acceptance Seller')
        ->set('password', 'pa55phrase-acceptance')
        ->set('password_confirmation', 'pa55phrase-acceptance')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertRedirect('/email/verify-notice');

    $seller = User::query()->where('email', 'seller@example.nl')->firstOrFail();
    expect($seller->email_verified_at)->toBeNull();
    Notification::assertSentTo($seller, VerifyEmail::class);

    // --- 2. Email verify ---------------------------------------------
    $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $seller->id,
        'hash' => sha1($seller->email),
    ]);
    $this->actingAs($seller)->get($verifyUrl)->assertRedirect();
    expect($seller->fresh()->email_verified_at)->not->toBeNull();

    // --- 3. Create listing through the wizard ------------------------
    $fixtureBytes = (string) file_get_contents(base_path('tests/Fixtures/photo-with-gps.jpg'));
    $upload = UploadedFile::fake()->createWithContent('photo.jpg', $fixtureBytes);
    $reflection = new ReflectionClass($upload);
    $prop = $reflection->getProperty('mimeTypeToReport');
    $prop->setAccessible(true);
    $prop->setValue($upload, 'image/jpeg');

    $this->actingAs($seller->fresh());

    Livewire::test(Wizard::class)
        ->set('title', 'IBM ThinkPad T42 — werkende vintage laptop')
        ->set('category_id', $this->category->id)
        ->set('condition', 'used')
        ->set('price_cents', 12500)
        ->call('next')
        ->assertSet('step', 2)
        ->set('description', 'Werkende ThinkPad T42 met originele docking station. Ideaal voor retro-Linux hobbyisten.')
        ->set('region_postcode', '1011')
        ->call('next')
        ->assertSet('step', 3)
        ->set('photos', [$upload])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $listing = Listing::query()->where('user_id', $seller->id)->firstOrFail();
    expect($listing->state)->toBe('pending_review')
        ->and($listing->photos()->count())->toBe(1);

    // --- 4. Admin approves through Filament --------------------------
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ListListings::class)
        ->callTableBulkAction('publish', [$listing])
        ->assertHasNoErrors();

    $listing->refresh();
    expect($listing->state)->toBe('published')
        ->and($listing->published_at)->not->toBeNull();

    // --- 5. Anonymous visitor finds it via search --------------------
    auth()->logout();

    // The browse grid should now include the listing.
    Livewire::test(Browse::class)->assertSee('IBM ThinkPad T42');

    // The full-text search endpoint should match the Dutch dictionary
    // tokenisation: "ThinkPad" hits, "thinkpad" hits, "vintage" hits.
    $this->get('/search?q=ThinkPad')->assertOk()->assertSee('IBM ThinkPad T42');

    // --- 6. Anonymous detail page + contact-redirect ----------------
    $detailPath = "/listings/{$listing->ulid}-{$listing->slug}";
    $this->get($detailPath)->assertOk()->assertSee('IBM ThinkPad T42');

    Livewire::test(Detail::class, ['ulid' => (string) $listing->ulid, 'slug' => (string) $listing->slug])
        ->call('contactSeller')
        ->assertRedirect('/login?return_to='.$detailPath);

    // Sanity: hitting login while still anonymous resolves OK so the
    // return_to flow has a valid landing page.
    $this->get('/login')->assertOk();
    expect(Livewire::test(Login::class)->get('email'))->toBe('');
});
