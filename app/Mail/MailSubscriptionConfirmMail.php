<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** De enige mail die naar een onbevestigd adres gaat. */
class MailSubscriptionConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MailSubscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isChangeRequest()
                ? 'Er is een wijziging aangevraagd'
                : 'Bevestig je aanmelding',
            replyTo: ['info@cloudmarktplaats.nl'],
        );
    }

    /**
     * De view leest `$subscription` als publieke property; `isWijziging` mag
     * wel via `with`, want die sleutel botst niet met een publieke property.
     * Zie DraftReminderMail: buildViewData() schrijft properties over `with`
     * heen, dus hergemapte sleutels verdwijnen daar stil.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.mail-subscription-confirm',
            with: ['isWijziging' => $this->isChangeRequest()],
        );
    }

    /**
     * Staat er een wijziging geparkeerd op een adres dat al bevestigd was, dan
     * kan die van een vreemde komen (geval 4 in MailSubscriptionService). De
     * ontvanger staat er dan al op en heeft dit misschien nooit gevraagd, dus
     * "bevestig je aanmelding" zou hem de verkeerde kant op duwen: hij moet
     * horen wát er is aangevraagd en dat negeren genoeg is.
     */
    public function isChangeRequest(): bool
    {
        return $this->subscription->confirmed_at !== null
            && is_array($this->subscription->pending_changes);
    }
}
