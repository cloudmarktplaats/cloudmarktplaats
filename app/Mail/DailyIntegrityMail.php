<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De dagelijkse integriteitsdigest. Bewust NIET queued: als de queue-worker
 * het probleem is, moet deze mail er juist wél uit.
 */
class DailyIntegrityMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, int>  $cijfers
     * @param  list<array{aantal: int, regel: string}>  $fouten
     * @param  list<string>  $signalen
     */
    public function __construct(
        public array $cijfers,
        public array $fouten,
        public array $signalen,
        public string $datum,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = $this->signalen === []
            ? 'Alles rustig'
            : count($this->signalen).' signaal'.(count($this->signalen) === 1 ? '' : 'en');

        return new Envelope(subject: "Cloudmarktplaats {$this->datum} — {$prefix}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-integrity');
    }
}
