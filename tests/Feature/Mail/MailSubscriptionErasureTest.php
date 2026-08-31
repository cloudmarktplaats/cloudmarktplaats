<?php

declare(strict_types=1);

use App\Models\MailSubscription;
use App\Models\User;
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
