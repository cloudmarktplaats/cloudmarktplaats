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
 * Herinnering aan een concept dat blijft staan.
 *
 * Op 19-08 stonden er 16 concepten van 10 verkopers tegenover 32 gepubliceerde
 * advertenties: een derde van alles wat er ligt is halfaf. Die mensen zijn niet
 * weg, ze hangen halverwege — en niemand vertelde ze dat er iets van hen
 * klaarstaat.
 *
 * Anders dan {@see ListingPhotoBugMail} is dit geen excuus maar een duw, en
 * hij is terugkerend in plaats van eenmalig. Daarom houdt `draft_reminded_at`
 * bij wie hem al kreeg: één herinnering per concept, nooit twee.
 *
 * Gegroepeerd per verkoper: iemand met drie concepten krijgt één mail.
 */
class DraftReminderMail extends Mailable implements ShouldQueue
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
                ? 'Je advertentie staat nog als concept'
                : 'Je advertenties staan nog als concept',
        );
    }

    /**
     * De view leest `$listings` (de publieke property) rechtstreeks en bouwt de
     * bewerk-URL's zelf. Niet via `with:` remappen — Mailable::buildViewData()
     * schrijft publieke properties óver de `with`-data heen, waardoor de
     * hergemapte sleutels stil verdwijnen. Zie ListingPhotoBugMail.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.draft-reminder');
    }
}
