<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;

/** De enige mail die naar een onbevestigd adres gaat. */
class MailSubscriptionConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MailSubscription $subscription) {}

    /**
     * Deze mail staat in de wachtrij en `SerializesModels` haalt de rij pas bij
     * het verzenden opnieuw op. Is er in de tussentijd op de link geklikt, dan
     * is `confirm_token` leeg en bestaat de link waar deze mail om draait niet
     * meer: renderen klapt dan op een ontbrekende routeparameter en de job
     * belandt in `failed_jobs`. De mail is op dat moment overbodig, dus stil
     * niets doen is het juiste gedrag, en niet een fout die iemand moet opruimen.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        if ($this->subscription->confirm_token === null) {
            return null;
        }

        return parent::send($mailer);
    }

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
