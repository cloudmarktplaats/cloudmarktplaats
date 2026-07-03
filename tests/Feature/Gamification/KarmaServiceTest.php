<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Gamification\KarmaService;

it('awards karma with an optional source', function () {
    $user = User::factory()->create();
    $source = User::factory()->create();

    $svc = app(KarmaService::class);
    $svc->award($user, 'invite_activation', 10, $source);

    expect($svc->karmaFor($user))->toBe(10)
        ->and($user->karmaEvents()->first()->source->is($source))->toBeTrue();
});

it('reverses an invitee activation exactly once', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $svc = app(KarmaService::class);
    $svc->award($inviter, 'invite_activation', 10, $invitee);

    $svc->revokeInviteActivation($invitee);
    $svc->revokeInviteActivation($invitee); // idempotent — no double reversal

    expect($svc->karmaFor($inviter))->toBe(0)
        ->and($inviter->karmaEvents()->count())->toBe(2); // +10 award, -10 reversal
});
