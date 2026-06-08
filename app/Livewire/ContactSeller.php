<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Mail\SellerContactMail;
use App\Models\ContactRelayLog;
use App\Models\Listing;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Seller-contact relay — the trade primitive, without an inbox.
 *
 * A buyer can reach a seller by one-way email. No account is required
 * (this backs the "optionally anonymous" principle): the buyer reveals
 * only their own address, and only by sending. The seller's address is
 * never exposed to the client; the message is handed straight to a
 * Mailable and never stored (see {@see ContactRelayLog}, which records
 * listing_id + timestamp only).
 *
 * Anti-spam, deliberately Google-free (no reCAPTCHA):
 *   - Honeypot: a hidden `website` field; if filled, a bot did it.
 *   - Timing trap: a form submitted under 2s after render is not human.
 *   - Rate limits: 5 messages / IP / hour and 3 / listing / IP / day.
 *
 * Spam that trips the honeypot or timing trap is dropped silently and
 * still shown the success state, so scrapers can't learn the tell.
 */
class ContactSeller extends Component
{
    public Listing $listing;

    public string $email = '';

    public string $body = '';

    /** Honeypot. Must stay empty; real users never see this field. */
    public string $website = '';

    /** Unix timestamp of form render, for the timing trap. */
    public int $formLoadedAt = 0;

    public bool $sent = false;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing;
        $this->formLoadedAt = now()->getTimestamp();
    }

    public function send(): void
    {
        // Honeypot tripped → bot. Pretend success, send nothing, log nothing.
        if ($this->website !== '') {
            $this->markSent();

            return;
        }

        // Submitted too fast to be a human → same silent drop.
        if (now()->getTimestamp() - $this->formLoadedAt < 2) {
            $this->markSent();

            return;
        }

        $this->validate([
            'email' => ['required', 'email:rfc'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: [
            'email' => 'e-mailadres',
            'body' => 'bericht',
        ]);

        // Hash the IP rather than key on it raw — consistent with the 24h
        // IP-retention promise enforced by IpStripperJob. The cache key is
        // the only place the IP touches, and it expires with the bucket.
        $ipHash = hash('sha256', (string) request()->ip().config('app.key'));
        $perIp = "contact-relay:ip:{$ipHash}";
        $perListing = "contact-relay:listing:{$this->listing->id}:{$ipHash}";

        if (RateLimiter::tooManyAttempts($perIp, 5) || RateLimiter::tooManyAttempts($perListing, 3)) {
            $this->addError('body', 'Je hebt te veel berichten verstuurd. Probeer het later opnieuw.');

            return;
        }

        RateLimiter::hit($perIp, 3600);
        RateLimiter::hit($perListing, 86400);

        // A published listing always has a seller; the guard keeps the
        // static analyser honest about the nullable relation.
        $seller = $this->listing->user;
        if ($seller !== null) {
            Mail::to($seller->email)->send(new SellerContactMail(
                listing: $this->listing,
                buyerEmail: $this->email,
                messageBody: $this->body,
            ));

            ContactRelayLog::create(['listing_id' => $this->listing->id]);
        }

        $this->markSent();
    }

    private function markSent(): void
    {
        $this->sent = true;
        $this->reset(['email', 'body', 'website']);
    }

    public function render(): View
    {
        return view('livewire.contact-seller');
    }
}
