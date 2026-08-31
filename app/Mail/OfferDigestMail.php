<?php

declare(strict_types=1);

namespace App\Mail;

use App\Console\Commands\SendOfferDigest;
use App\Models\Listing;
use App\Models\MailSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Het nieuwe aanbod van deze week in de categorieen die de ontvanger aanvinkte.
 *
 * Deze mail bestaat alleen als er iets te melden is: {@see SendOfferDigest}
 * maakt hem niet aan bij een lege lijst. Er is dus geen "deze week niets"-variant.
 *
 * Geen tracking. Geen open-pixel, geen omgeleide links: de advertenties linken
 * rechtstreeks naar hun eigen pagina. De enige unieke link per ontvanger is het
 * afmeldtoken, en die is er omdat afmelden moet werken, niet om te meten.
 */
class OfferDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Listing>  $listings
     */
    public function __construct(
        public MailSubscription $subscription,
        public Collection $listings,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->listings->count() === 1
                ? 'Nieuw aanbod: 1 advertentie'
                : 'Nieuw aanbod: '.$this->listings->count().' advertenties',
            replyTo: ['info@cloudmarktplaats.nl'],
        );
    }

    /**
     * Afmelden in 1 klik vanuit de mailclient zelf, naast de link in de voet.
     * De header wijst naar het hele afmeldscherm zonder `wat`: een client die
     * hier op drukt bedoelt alles, en dat scherm biedt de keuze nog terug aan.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.route('mail.unsubscribe', $this->subscription->unsubscribe_token).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * De view leest `$subscription` en `$listings` als publieke property. Niet
     * via `with:` hernoemen: Mailable::buildViewData() schrijft publieke
     * properties over de `with`-data heen. Zie ListingPhotoBugMail.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.offer-digest');
    }
}
