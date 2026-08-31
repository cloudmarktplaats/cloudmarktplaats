<?php

declare(strict_types=1);

namespace App\Mail;

use App\Console\Commands\SendPlatformUpdate;
use App\Models\MailSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * De nieuwsbrief, met de tekst die {@see SendPlatformUpdate} uit een
 * markdownbestand leest. Hooguit 1 keer per 30 dagen; die rem zit in het
 * commando en niet hier.
 *
 * Geen tracking. Geen open-pixel, geen omgeleide links. De enige unieke link
 * per ontvanger is het afmeldtoken, en die is er omdat afmelden moet werken,
 * niet om te meten.
 */
class PlatformUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MailSubscription $subscription,
        public string $tekst,
    ) {}

    /**
     * De eerste kop uit de tekst is het onderwerp: dat is de zin die de
     * schrijver zelf al bedacht. Staat er geen kop, dan de vaste vorm, want een
     * mail zonder onderwerp leest als een storing.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kop() ?: 'Update van Cloudmarktplaats',
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
     * De view leest `$subscription` en `$tekst` als publieke property. Niet via
     * `with:` hernoemen: Mailable::buildViewData() schrijft publieke properties
     * over de `with`-data heen. Zie ListingPhotoBugMail.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.platform-update');
    }

    /** De eerste regel die met `#` begint, zonder de hekjes. */
    private function kop(): ?string
    {
        foreach (explode("\n", $this->tekst) as $regel) {
            if (Str::startsWith(trim($regel), '#')) {
                return trim(ltrim(trim($regel), '# '));
            }
        }

        return null;
    }
}
