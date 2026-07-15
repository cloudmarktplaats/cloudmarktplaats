<?php

declare(strict_types=1);

use App\Exceptions\InviteException;
use App\Models\User;
use App\Services\Gamification\InviteService;

function verifiedUser(int $credits = 3): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
        'invite_credits' => $credits,
    ]);
}

it('generates a code and spends a credit', function () {
    $inviter = verifiedUser(2);
    $code = app(InviteService::class)->generate($inviter);

    expect($code->inviter_user_id)->toBe($inviter->id)
        ->and($inviter->refresh()->invite_credits)->toBe(1);
});

it('refuses to generate without credits', function () {
    $inviter = verifiedUser(0);

    expect(fn () => app(InviteService::class)->generate($inviter))
        ->toThrow(InviteException::class);
});

it('redeems a code and links the invitee', function () {
    $inviter = verifiedUser();
    $code = app(InviteService::class)->generate($inviter);
    $invitee = User::factory()->create();

    app(InviteService::class)->redeem($code->code, $invitee);

    expect($invitee->refresh()->invited_by)->toBe($inviter->id)
        ->and($code->refresh()->invitee_user_id)->toBe($invitee->id)
        ->and($code->used_at)->not->toBeNull();
});

it('rejects a reused, self, or unknown code', function () {
    $inviter = verifiedUser();
    $code = app(InviteService::class)->generate($inviter);
    $invitee = User::factory()->create();
    app(InviteService::class)->redeem($code->code, $invitee);

    // reused
    $other = User::factory()->create();
    expect(fn () => app(InviteService::class)->redeem($code->code, $other))
        ->toThrow(InviteException::class);

    // unknown
    expect(fn () => app(InviteService::class)->redeem('NOPE000000', $other))
        ->toThrow(InviteException::class);

    // self-invite
    $selfCode = app(InviteService::class)->generate($inviter);
    expect(fn () => app(InviteService::class)->redeem($selfCode->code, $inviter))
        ->toThrow(InviteException::class);
});

it('rejects redeeming a second code for an already-invited user', function () {
    $inviterA = verifiedUser();
    $inviterB = verifiedUser();
    $codeA = app(InviteService::class)->generate($inviterA);
    $codeB = app(InviteService::class)->generate($inviterB);
    $invitee = User::factory()->create();

    app(InviteService::class)->redeem($codeA->code, $invitee);

    expect(fn () => app(InviteService::class)->redeem($codeB->code, $invitee->fresh()))
        ->toThrow(InviteException::class);

    // The second code stays unused — no double credit.
    expect($codeB->refresh()->used_at)->toBeNull();
});

/**
 * Codes are generated uppercase (InviteCode::booted), but Postgres compares
 * case-sensitively — so someone retyping their code by hand in lowercase used
 * to get "ongeldig of al gebruikt" for a perfectly valid invite. The invite
 * link carries the right casing; typing it does not.
 *
 * Same bug class as the email one fixed on 2026-07-15 (commit 38a9e4f).
 */
it('redeems a code regardless of how the invitee typed it', function () {
    $inviter = verifiedUser();
    $code = app(InviteService::class)->generate($inviter);

    expect($code->code)->toBe(strtoupper($code->code));

    $invitee = User::factory()->create();
    $redeemed = app(InviteService::class)->redeem(strtolower($code->code), $invitee);

    expect($redeemed->id)->toBe($code->id)
        ->and($redeemed->fresh()->used_at)->not->toBeNull();
});

it('still rejects a code that simply does not exist', function () {
    $invitee = User::factory()->create();

    // Normalising must not turn "unknown" into "found".
    expect(fn () => app(InviteService::class)->redeem('bestaatniet', $invitee))
        ->toThrow(InviteException::class);
});
