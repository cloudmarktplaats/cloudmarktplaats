<?php

declare(strict_types=1);

use App\Mail\MailSubscriptionConfirmMail;
use App\Models\MailSubscription;
use Illuminate\Support\Facades\Mail;

/*
 * Deze mail is ShouldQueue met SerializesModels, dus de rij wordt pas bij het
 * verzenden opnieuw uit de database gehaald. Heeft de ontvanger in de tussentijd
 * op de link geklikt, dan is `confirm_token` leeg en bestaat de link waar de
 * hele mail om draait niet meer: het renderen klapt dan op een ontbrekende
 * routeparameter en de job belandt in `failed_jobs`.
 *
 * Hier geen Mail::fake(): die vangt de mail af vóór het verzenden, en dan wordt
 * juist het stuk dat stukgaat niet uitgevoerd. De array-transport uit
 * phpunit.xml verstuurt echt, alleen naar het geheugen.
 */
it('sends nothing once the confirmation link has been used', function () {
    $sub = MailSubscription::factory()->create([
        'email' => 'alklaar@example.test',
        'confirmed_at' => now(),
        'confirm_token' => null,
    ]);

    Mail::to($sub->email)->send(new MailSubscriptionConfirmMail($sub));

    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(0);
});

/* Tegenproef: met een levend token gaat hij gewoon de deur uit. */
it('still sends while the confirmation link is alive', function () {
    $sub = MailSubscription::factory()->unconfirmed()->create([
        'email' => 'onderweg@example.test',
    ]);

    Mail::to($sub->email)->send(new MailSubscriptionConfirmMail($sub));

    expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(1);
});
