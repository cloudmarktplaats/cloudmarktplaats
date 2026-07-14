<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One-off apology to sellers whose listing got stuck as a draft because the
 * photo upload crashed.
 *
 * Context: StoreListingPhotoJob decoded the upload and immediately cloned it,
 * which exceeded PHP's 128M limit for any phone-sized photo (a 12MP image is
 * 48MB per copy in GD). A listing cannot be published without a photo, so
 * these sellers filled in every field and then hit a wall. Only small
 * screenshots ever got through.
 *
 * Grouped per seller, not per listing: someone with two stuck drafts gets one
 * mail listing both, not two apologies.
 *
 * Queued so a slow SMTP round-trip doesn't stall the console command.
 */
class ListingPhotoBugMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Listing>  $listings
     */
    public function __construct(
        public User $user,
        public Collection $listings,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->listings->count() === 1
                ? 'Je advertentie staat nog als concept — dat lag aan ons'
                : 'Je advertenties staan nog als concept — dat lag aan ons',
        );
    }

    /**
     * The view reads `$listings` (this class's public property) directly and
     * builds each edit URL itself.
     *
     * Do NOT pass a remapped array via `with: ['listings' => ...]`: Mailable's
     * buildViewData() writes public properties *over* the `with` data, so the
     * property silently wins and the remapped keys vanish — which is exactly
     * how this mail first rendered its buttons with an empty href.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.listing-photo-bug');
    }
}
