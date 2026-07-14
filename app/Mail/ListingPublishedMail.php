<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Listing;
use App\Support\ShareLinkBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Told the seller their listing passed moderation and is now live.
 *
 * Queued so a moderator clicking "publish" in Filament never waits on SMTP —
 * and a mail failure can't roll back the state transition, which has already
 * been persisted by the time ListingPublished fires.
 *
 * The mail deliberately carries no share buttons: clipboard access needs JS,
 * which mail clients don't run. It links to the listing, where the share
 * panel does the work.
 */
class ListingPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je advertentie staat live: '.$this->listing->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.listing-published',
            with: [
                'title' => $this->listing->title,
                'url' => app(ShareLinkBuilder::class)->emailUrl($this->listing),
                'photoUrl' => $this->listing->photos->first()?->urlFor('card'),
            ],
        );
    }
}
