<?php

declare(strict_types=1);

use App\Mail\WaitlistInviteMail;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
        'invite_credits' => 0, // deliberately broke: this must not stop us
    ]);
});

it('mails everyone on the waitlist a working invite link', function () {
    $entry = WaitlistEntry::query()->create(['email' => 'edu@sincere.nl']);

    $this->artisan('waitlist:invite')->assertSuccessful();

    Mail::assertQueued(WaitlistInviteMail::class, function (WaitlistInviteMail $mail) use ($entry) {
        return $mail->hasTo($entry->email);
    });
    expect($entry->fresh()->invited)->toBeTrue()
        ->and(InviteCode::query()->count())->toBe(1);
});

it('sends nothing with --dry-run', function () {
    $entry = WaitlistEntry::query()->create(['email' => 'edu@sincere.nl']);

    $this->artisan('waitlist:invite', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingQueued();
    expect($entry->fresh()->invited)->toBeFalse()
        ->and(InviteCode::query()->count())->toBe(0);
});

it('does not invite the same person twice', function () {
    WaitlistEntry::query()->create(['email' => 'al-gehad@example.com', 'invited' => true]);
    WaitlistEntry::query()->create(['email' => 'nieuw@example.com']);

    $this->artisan('waitlist:invite')->assertSuccessful();

    // `invited` is the ledger: a second run must only reach the new arrival.
    Mail::assertQueued(WaitlistInviteMail::class, 1);
    Mail::assertQueued(WaitlistInviteMail::class, fn (WaitlistInviteMail $m): bool => $m->hasTo('nieuw@example.com'));
});

it('works even when the inviter has no invite credits left', function () {
    // Credits are a gamification budget for members inviting friends. Clearing
    // your own waitlist is an operator action: 9 credits and 15 people waiting
    // must not mean 6 people keep waiting.
    expect($this->admin->invite_credits)->toBe(0);

    WaitlistEntry::query()->create(['email' => 'een@example.com']);
    WaitlistEntry::query()->create(['email' => 'twee@example.com']);

    $this->artisan('waitlist:invite')->assertSuccessful();

    Mail::assertQueued(WaitlistInviteMail::class, 2);
    expect($this->admin->fresh()->invite_credits)->toBe(0);
});

it('records who invited them so karma and the invite graph stay intact', function () {
    WaitlistEntry::query()->create(['email' => 'edu@sincere.nl']);

    $this->artisan('waitlist:invite')->assertSuccessful();

    expect(InviteCode::query()->first()->inviter_user_id)->toBe($this->admin->id);
});

it('refuses to invite from a banned account', function () {
    $this->admin->forceFill(['is_banned' => true])->save();
    WaitlistEntry::query()->create(['email' => 'edu@sincere.nl']);

    $this->artisan('waitlist:invite')->assertFailed();

    Mail::assertNothingQueued();
});

it('the mailed link actually carries the code', function () {
    $code = InviteCode::query()->create(['inviter_user_id' => $this->admin->id]);

    $rendered = (new WaitlistInviteMail($code))->render();

    expect($rendered)->toContain('/register?invite='.$code->code);
});

it('says so plainly when nobody is waiting', function () {
    $this->artisan('waitlist:invite')
        ->expectsOutputToContain('Niemand op de wachtlijst')
        ->assertSuccessful();
});
