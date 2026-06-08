<?php

declare(strict_types=1);

use App\Livewire\ContactSeller;
use App\Mail\SellerContactMail;
use App\Models\ContactRelayLog;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** A listing whose seller has a known address, plus a "loaded long enough ago" form. */
function relayForm(?Listing $listing = null): Testable
{
    $listing ??= Listing::factory()->published()->for(
        User::factory()->create(['email' => 'verkoper@example.test'])
    )->create(['title' => 'Dell R720 server']);

    return Livewire::test(ContactSeller::class, ['listing' => $listing])
        // Pretend the form has been on screen for a while so the timing
        // trap does not fire on legitimate submissions.
        ->set('formLoadedAt', now()->subSeconds(10)->timestamp);
}

it('relays a message to the seller with the buyer as reply-to', function () {
    Mail::fake();

    $listing = Listing::factory()->published()->for(
        User::factory()->create(['email' => 'verkoper@example.test'])
    )->create(['title' => 'Dell R720 server']);

    relayForm($listing)
        ->set('email', 'koper@example.test')
        ->set('body', 'Is deze nog beschikbaar en wat is de laagste prijs?')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Mail::assertSent(SellerContactMail::class, function (SellerContactMail $mail) {
        return $mail->hasTo('verkoper@example.test')
            && $mail->hasReplyTo('koper@example.test')
            && $mail->listing->title === 'Dell R720 server';
    });
});

it('silently drops submissions that fill the honeypot', function () {
    Mail::fake();

    relayForm()
        ->set('email', 'bot@example.test')
        ->set('body', 'Koop nu goedkope horloges op spam.example')
        ->set('website', 'http://spam.example') // honeypot — humans never see this
        ->call('send')
        ->assertSet('sent', true);

    Mail::assertNothingSent();
    expect(ContactRelayLog::count())->toBe(0);
});

it('silently drops submissions sent faster than a human could type', function () {
    Mail::fake();

    Livewire::test(ContactSeller::class, [
        'listing' => Listing::factory()->published()->create(),
    ])
        // formLoadedAt defaults to "now" on mount → elapsed < 2s.
        ->set('email', 'fast@example.test')
        ->set('body', 'Submitted instantly by a script.')
        ->call('send')
        ->assertSet('sent', true);

    Mail::assertNothingSent();
});

it('validates the email address and message length', function () {
    Mail::fake();

    relayForm()
        ->set('email', 'not-an-email')
        ->set('body', 'kort')
        ->call('send')
        ->assertHasErrors(['email', 'body'])
        ->assertSet('sent', false);

    Mail::assertNothingSent();
});

it('logs only the listing id — no email, no message body', function () {
    Mail::fake();

    $listing = Listing::factory()->published()->create();

    relayForm($listing)
        ->set('email', 'koper@example.test')
        ->set('body', 'Een nette vraag over de advertentie.')
        ->call('send')
        ->assertSet('sent', true);

    $columns = Schema::getColumnListing('contact_relay_logs');
    sort($columns);
    expect($columns)->toBe(['created_at', 'id', 'listing_id']);

    $row = DB::table('contact_relay_logs')->first();
    expect($row->listing_id)->toBe($listing->id)
        ->and((array) $row)->not->toHaveKeys(['email', 'body', 'ip', 'message']);
});

it('blocks more than three messages to the same listing from one sender', function () {
    Mail::fake();
    RateLimiter::clear('test'); // defensive; keys are hashed below

    $listing = Listing::factory()->published()->for(
        User::factory()->create(['email' => 'verkoper@example.test'])
    )->create();

    foreach (range(1, 3) as $i) {
        relayForm($listing)
            ->set('email', 'koper@example.test')
            ->set('body', "Bericht nummer {$i} over deze advertentie.")
            ->call('send')
            ->assertSet('sent', true);
    }

    // Fourth attempt to the same listing trips the per-listing/day cap.
    relayForm($listing)
        ->set('email', 'koper@example.test')
        ->set('body', 'Vierde bericht dat geweigerd moet worden.')
        ->call('send')
        ->assertHasErrors('body')
        ->assertSet('sent', false);

    Mail::assertSent(SellerContactMail::class, 3);
});
