<?php

declare(strict_types=1);

use App\Models\InviteCode;
use App\Models\KarmaEvent;
use App\Models\User;

it('auto-generates a code and scopes redeemable', function () {
    $open = InviteCode::factory()->create();
    InviteCode::factory()->used()->create();
    InviteCode::factory()->create(['expires_at' => now()->subDay()]);

    expect($open->code)->toBeString()->not->toBe('')
        ->and(InviteCode::query()->redeemable()->pluck('id')->all())->toBe([$open->id]);
});

it('sums karma from the ledger', function () {
    $user = User::factory()->create();
    KarmaEvent::factory()->for($user)->create(['points' => 10]);
    KarmaEvent::factory()->for($user)->create(['points' => -10]);
    KarmaEvent::factory()->for($user)->create(['points' => 5]);

    expect($user->refresh()->karma)->toBe(5);
});

it('links an invite tree', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);

    expect($invitee->invitedBy->is($inviter))->toBeTrue()
        ->and($inviter->invitesSent)->toHaveCount(0); // invitesSent = codes, not users
});
