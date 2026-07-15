<?php

declare(strict_types=1);

use App\Mail\OAuthAccountRepairedMail;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/** An account the migration repaired: signed up days ago, verified just now. */
function repairedOAuthUser(string $email = 'stuck@example.test'): User
{
    $user = User::factory()->create([
        'email' => $email,
        'created_at' => now()->subDays(6),
        'email_verified_at' => now(),
    ]);
    UserIdentity::create(['user_id' => $user->id, 'provider' => 'oauth_github', 'provider_uid' => uniqid()]);

    return $user;
}

it('mails someone whose oauth account was repaired', function () {
    $user = repairedOAuthUser();

    $this->artisan('oauth:notify-repaired')->assertSuccessful();

    Mail::assertQueued(OAuthAccountRepairedMail::class, fn (OAuthAccountRepairedMail $m): bool => $m->hasTo($user->email));
});

it('sends nothing with --dry-run', function () {
    repairedOAuthUser();

    $this->artisan('oauth:notify-repaired', ['--dry-run' => true])->assertSuccessful();

    Mail::assertNothingQueued();
});

it('leaves a healthy oauth signup alone', function () {
    // Post-fix signups are verified in the same request, so created_at and
    // email_verified_at sit together — no gap, nothing was ever broken.
    $user = User::factory()->create(['created_at' => now(), 'email_verified_at' => now()]);
    UserIdentity::create(['user_id' => $user->id, 'provider' => 'oauth_github', 'provider_uid' => 'fresh']);

    $this->artisan('oauth:notify-repaired')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('leaves password accounts alone', function () {
    // Someone who clicked their verification mail a week later fits the time
    // gap exactly — but they were never broken, and telling them "our fault"
    // would be nonsense. The oauth_* identity is what separates the two.
    $user = User::factory()->create([
        'created_at' => now()->subDays(6),
        'email_verified_at' => now(),
    ]);
    UserIdentity::create(['user_id' => $user->id, 'provider' => 'password', 'provider_uid' => (string) $user->id]);

    $this->artisan('oauth:notify-repaired')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('leaves placeholder-address accounts alone', function () {
    // uid@github.local means the provider gave us no address: mail goes nowhere.
    $user = User::factory()->create([
        'email' => '777@github.local',
        'created_at' => now()->subDays(6),
        'email_verified_at' => now(),
    ]);
    UserIdentity::create(['user_id' => $user->id, 'provider' => 'oauth_github', 'provider_uid' => '777']);

    $this->artisan('oauth:notify-repaired')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('links straight to placing a listing — the thing they could not do', function () {
    $user = repairedOAuthUser();

    $rendered = (new OAuthAccountRepairedMail($user))->render();

    expect($rendered)
        ->toContain('/listings/new')
        ->toContain('lag niet aan jou');
});
