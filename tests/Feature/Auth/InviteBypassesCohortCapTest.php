<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Services\Gamification\InviteService;
use App\Services\Gamification\StatsService;
use Livewire\Livewire;

/**
 * An invite is a member vouching for someone — it must open the door even when
 * the founding cohort is full. Without that, the whole invite system dies the
 * moment the cap is reached: on 2026-07-15 prod sat at 100/100 with 305 unused
 * invite credits, 2 codes handed out and 0 ever redeemed, because
 * Register::submit() aborted with a 403 before the code was even looked at —
 * and the page showed the waitlist instead of the form, so an invited person
 * could not even enter their code.
 *
 * The founding badge stays capped at the first 100: the door opens, the badge
 * does not.
 */
function fillCohort(): void
{
    User::factory()->count(StatsService::FOUNDING_COHORT)->create();
}

function validCodeFrom(User $inviter): string
{
    return app(InviteService::class)->generate($inviter)->code;
}

it('sends someone without an invite to the waitlist once the cohort is full', function () {
    fillCohort();

    Livewire::test(Register::class)
        ->assertSet('waitlisted', false)
        ->assertViewHas('registrationOpen', false);
});

it('shows the registration form to someone arriving with a valid invite', function () {
    fillCohort();
    $inviter = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 1]);
    $code = validCodeFrom($inviter);

    // The invite link is /register?invite=CODE — mount() reads it from the query.
    Livewire::withQueryParams(['invite' => $code])
        ->test(Register::class)
        ->assertViewHas('registrationOpen', true);
});

it('still shows the waitlist for a bogus invite code', function () {
    fillCohort();

    Livewire::withQueryParams(['invite' => 'BESTAATNIET'])
        ->test(Register::class)
        ->assertViewHas('registrationOpen', false);
});

it('lets an invited person register even though the cohort is full', function () {
    fillCohort();
    $inviter = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 1]);
    $code = validCodeFrom($inviter);

    Livewire::test(Register::class)
        ->set('invite_code', $code)
        ->set('email', 'edu@sincere.nl')
        ->set('username', 'edu')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasNoErrors();

    $edu = User::query()->where('email', 'edu@sincere.nl')->first();
    expect($edu)->not->toBeNull()
        ->and($edu->invited_by)->toBe($inviter->id);
});

it('does not hand a founding badge to someone who arrives after the first 100', function () {
    fillCohort();
    $inviter = User::factory()->create(['email_verified_at' => now(), 'invite_credits' => 1]);
    $code = validCodeFrom($inviter);

    Livewire::test(Register::class)
        ->set('invite_code', $code)
        ->set('email', 'laat@example.com')
        ->set('username', 'laatkomer')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasNoErrors();

    // The door opens; the badge does not. "De eerste 100" stays true.
    expect(User::query()->where('email', 'laat@example.com')->first()->is_founding_member)->toBeFalse();
});

it('refuses to register without an invite when the cohort is full', function () {
    fillCohort();

    Livewire::test(Register::class)
        ->set('email', 'ongenood@example.com')
        ->set('username', 'ongenood')
        ->set('password', 'een-lang-genoeg-wachtwoord')
        ->set('password_confirmation', 'een-lang-genoeg-wachtwoord')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertStatus(403);

    expect(User::query()->where('email', 'ongenood@example.com')->exists())->toBeFalse();
});
