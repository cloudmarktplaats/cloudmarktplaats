<?php

declare(strict_types=1);

use App\Mail\ListingPublishedMail;
use App\Models\Listing;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\Mail;

it('queues a mail to the seller when their listing is published', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    app(ListingStateService::class)->transition($listing, 'published');

    Mail::assertQueued(ListingPublishedMail::class, function (ListingPublishedMail $mail) use ($listing) {
        return $mail->hasTo($listing->user->email)
            && $mail->listing->is($listing);
    });
});

it('does not mail when a listing is rejected', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);

    app(ListingStateService::class)->transition($listing, 'rejected', 'Onvoldoende foto\'s');

    Mail::assertNothingQueued();
});

it('mails again when a rejected listing is resubmitted and approved', function () {
    Mail::fake();
    $listing = Listing::factory()->create(['state' => 'pending_review']);
    $svc = app(ListingStateService::class);

    $svc->transition($listing, 'rejected', 'Onvoldoende foto\'s');
    $svc->transition($listing->fresh(), 'draft');
    $svc->transition($listing->fresh(), 'pending_review');
    $svc->transition($listing->fresh(), 'published');

    // Deliberate: the seller is told their listing is now genuinely approved.
    Mail::assertQueued(ListingPublishedMail::class, 1);
});

it('tags the listing link in the mail as email/listing_published', function () {
    $listing = Listing::factory()->create(['state' => 'published']);

    $rendered = (new ListingPublishedMail($listing))->render();

    expect($rendered)
        ->toContain('utm_source=email')
        ->toContain('utm_campaign=listing_published');
});
