<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use App\Services\Profile\AccountRemovalService;

/*
 * Post krijgen van een platform waar je net vertrokken bent is precies de fout
 * die op 21-08 een lid kostte. Accountverwijdering moet de inschrijving dus
 * echt meenemen, en niet alleen `deleted_at` zetten.
 */
it('removes the mailing list subscription when the account is erased', function () {
    $user = User::factory()->create(['email' => 'nick@example.test']);
    MailSubscription::factory()->create([
        'user_id' => $user->id,
        'email' => 'nick@example.test',
    ]);

    app(AccountRemovalService::class)->remove($user);

    expect(MailSubscription::query()->where('email', 'nick@example.test')->exists())->toBeFalse();
});

it('keeps a subscription that never belonged to an account', function () {
    MailSubscription::factory()->create(['user_id' => null, 'email' => 'los@example.test']);
    $user = User::factory()->create();

    app(AccountRemovalService::class)->remove($user);

    expect(MailSubscription::query()->where('email', 'los@example.test')->exists())->toBeTrue();
});

/*
 * Mutatietest: `'user_id' => $owner?->id ?? $sub->user_id` teruggedraaid naar
 * `$owner?->id` laat elke andere test in deze suite groen, want geen daarvan
 * laat een anonieme aanmelding over een bestaande, al gekoppelde rij heen
 * gaan. Precies dat pad is hier: een onbevestigde rij hangt al aan een
 * account, iemand vult hetzelfde adres anoniem opnieuw in, en de koppeling
 * moet blijven staan, anders grijpt de wiscascade hierboven niet meer.
 */
it('keeps the account link through an anonymous resubscribe, so erasure still takes the row', function () {
    $user = User::factory()->create(['email' => 'lid@example.test']);
    MailSubscription::factory()->unconfirmed()->create([
        'email' => 'lid@example.test',
        'user_id' => $user->id,
    ]);

    app(MailSubscriptionService::class)->subscribe(
        email: 'lid@example.test',
        wantsOffers: true,
        wantsUpdates: true,
        categories: [],
        consentText: 'Ja, mail mij nieuw aanbod in deze categorieen.',
        source: 'formulier',
    );

    expect(MailSubscription::query()->where('email', 'lid@example.test')->first()?->user_id)->toBe($user->id);

    app(AccountRemovalService::class)->remove($user);

    expect(MailSubscription::query()->where('email', 'lid@example.test')->exists())->toBeFalse();
});
