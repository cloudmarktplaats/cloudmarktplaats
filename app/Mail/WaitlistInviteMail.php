<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\InviteCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells someone on the waitlist their spot is ready, with a working invite link.
 *
 * The founding cohort filled up on 2026-07-15 and registration closed behind a
 * waitlist. An invite now opens that door (see Register::submit), so this mail
 * is what turns a waiting address into an actual member.
 *
 * Queued: a console command sends a batch of these, and it should not sit
 * waiting on SMTP for each one.
 */
class WaitlistInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public InviteCode $inviteCode) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je plek op Cloudmarktplaats staat klaar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-invite',
            with: [
                'url' => url('/register?invite='.$this->inviteCode->code),
                'code' => $this->inviteCode->code,
            ],
        );
    }
}
