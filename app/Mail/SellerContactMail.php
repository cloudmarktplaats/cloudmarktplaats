<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-way contact relay: a buyer's message to a seller.
 *
 * The seller stays fully shielded — the buyer never learns their address.
 * The buyer reveals their own address only by sending: it becomes the
 * Reply-To, so the seller can answer directly and the conversation
 * continues off-platform from there. We pass the buyer email and message
 * body by value (not a model) precisely because nothing is persisted —
 * the body lives only in this email, never in our database.
 */
class SellerContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Listing $listing,
        public string $buyerEmail,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bericht over je advertentie: '.$this->listing->title,
            replyTo: [new Address($this->buyerEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-contact',
            with: [
                'title' => $this->listing->title,
                'url' => url('/listings/'.$this->listing->ulid.'-'.$this->listing->slug),
                'body' => $this->messageBody,
            ],
        );
    }
}
