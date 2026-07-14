<?php

declare(strict_types=1);

namespace App\Listeners\Listings;

use App\Events\Listings\ListingPublished;
use App\Mail\ListingPublishedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Mails the seller when their listing goes live.
 *
 * No dedupe: a listing can legitimately reach `published` more than once
 * (rejected → draft → pending_review → published), and being told your
 * listing is now actually approved is the point.
 */
class SendListingPublishedMail
{
    public function handle(ListingPublished $event): void
    {
        $owner = $event->listing->user;

        if (! $owner instanceof User || $owner->email === null) {
            return;
        }

        // The Mailable is ShouldQueue, so this only pushes onto the queue.
        Mail::to($owner->email)->send(new ListingPublishedMail($event->listing));
    }
}
