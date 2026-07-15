<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells someone who signed in with GitHub that their account works now.
 *
 * They could not use it: OAuthController passed `email_verified_at` to
 * User::create() where the column is not fillable, so it was dropped silently
 * and the account stayed unverified — and that flow sends no verification mail,
 * so there was no way out. Both listing and inviting require `verified`. They
 * had no way of knowing any of this; from their side the site simply did not
 * work. Fixed 2026-07-15; 14 accounts had been stuck up to six days.
 */
class OAuthAccountRepairedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je account werkt weer — het lag aan ons',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.oauth-account-repaired',
            with: [
                'newListingUrl' => url('/listings/new'),
            ],
        );
    }
}
