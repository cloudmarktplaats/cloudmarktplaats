<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

/*
 * De dagelijkse check meldde op 02-09-2026: "1 deal wacht nog op bevestiging
 * terwijl er geen bruikbare claim-link meer is — de verkoper kan een nieuwe
 * sturen." Dat laatste bleek niet te kloppen voor die ene rij.
 *
 * Het gaat om een verkoop van vóór de claim-link: `claim_token` en
 * `claim_expires_at` zijn allebei NULL. In `claim-link.blade.php` stond
 *
 *     $expired = $transaction->claim_expires_at?->isPast() ?? false;
 *
 * en op een NULL-datum geeft dat `false`. De verkoper kreeg dus niet de knop
 * "Nieuwe link" te zien maar de tak voor een geldige link, met een URL waar
 * geen token in zit. De mail droeg hem op iets te doen dat het scherm niet
 * aanbood, elke dag opnieuw.
 *
 * Dit is dezelfde soort fout als het privacybeleid dat verwijdering beloofde
 * die niet bestond: een belofte zonder code eronder is een schuld.
 */

it('offers a fresh link for a sale that predates the claim token', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller, 'user')->create(['state' => 'sold']);

    /* Een rij zoals hij vóór de claim-link werd aangemaakt: geen token, geen
       vervaldatum. */
    $tx = Transaction::factory()->create([
        'listing_id' => $listing->id,
        'seller_user_id' => $seller->id,
        'status' => 'pending',
        'claim_token' => null,
        'claim_expires_at' => null,
    ]);

    $html = Blade::render(
        '<x-deals.claim-link :transaction="$transaction" />',
        ['transaction' => $tx->fresh()]
    );

    expect($html)->toContain(__('Nieuwe link'))
        ->and($html)->not->toContain(__('Kopieer link + tekst'));
});

it('still shows the copyable link when the token is valid', function () {
    $seller = User::factory()->create();
    $listing = Listing::factory()->for($seller, 'user')->create(['state' => 'sold']);

    $tx = Transaction::factory()->create([
        'listing_id' => $listing->id,
        'seller_user_id' => $seller->id,
        'status' => 'pending',
        'claim_token' => str_repeat('a', 32),
        'claim_expires_at' => now()->addDays(10),
    ]);

    $html = Blade::render(
        '<x-deals.claim-link :transaction="$transaction" />',
        ['transaction' => $tx->fresh()]
    );

    expect($html)->toContain(__('Kopieer link + tekst'))
        ->and($html)->not->toContain(__('Nieuwe link'));
});
