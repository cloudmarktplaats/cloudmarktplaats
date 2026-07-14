<?php

declare(strict_types=1);

use App\Mail\ListingPhotoBugMail;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('mails the owner of a draft that has no photos', function () {
    $listing = Listing::factory()->create(['state' => 'draft']);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    Mail::assertQueued(ListingPhotoBugMail::class, function (ListingPhotoBugMail $mail) use ($listing) {
        return $mail->hasTo($listing->user->email)
            && $mail->listings->contains(fn (Listing $l): bool => $l->is($listing));
    });
});

it('sends nothing at all with --dry-run', function () {
    Listing::factory()->create(['state' => 'draft']);

    $this->artisan('listings:notify-photo-bug', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingQueued();
});

it('skips drafts that already have a photo', function () {
    $listing = Listing::factory()->create(['state' => 'draft']);
    ListingPhoto::factory()->for($listing)->create();

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('leaves published and pending listings alone', function () {
    Listing::factory()->create(['state' => 'published']);
    Listing::factory()->create(['state' => 'pending_review']);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends one mail per seller, not one per listing', function () {
    $owner = User::factory()->create();
    Listing::factory()->count(2)->create(['state' => 'draft', 'user_id' => $owner->id]);

    $this->artisan('listings:notify-photo-bug')->assertSuccessful();

    // Two stuck drafts, one apology.
    Mail::assertQueued(ListingPhotoBugMail::class, 1);
    Mail::assertQueued(ListingPhotoBugMail::class, function (ListingPhotoBugMail $mail): bool {
        return $mail->listings->count() === 2;
    });
});

it('honours --exclude so test drafts can be left out', function () {
    $skipped = Listing::factory()->create(['state' => 'draft']);
    $mailed = Listing::factory()->create(['state' => 'draft']);

    $this->artisan('listings:notify-photo-bug', ['--exclude' => [$skipped->user_id]])->assertSuccessful();

    Mail::assertQueued(ListingPhotoBugMail::class, 1);
    Mail::assertQueued(ListingPhotoBugMail::class, function (ListingPhotoBugMail $mail) use ($mailed): bool {
        return $mail->hasTo($mailed->user->email);
    });
});

it('links each listing straight to its wizard edit page', function () {
    $listing = Listing::factory()->create(['state' => 'draft', 'title' => 'Synology NAS DS413']);

    $rendered = (new ListingPhotoBugMail($listing->user, collect([$listing])))->render();

    expect($rendered)
        ->toContain('Synology NAS DS413')
        ->toContain("/listings/{$listing->ulid}/edit")
        ->toContain('github.com/cloudmarktplaats/cloudmarktplaats/issues');
});
