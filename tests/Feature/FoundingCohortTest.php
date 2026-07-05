<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\FoundingCohort;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StatsService;
use App\Services\Gamification\StatsService as Stats;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('stamps early registrations as founding members', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('email', 'early@b.nl')->set('username', 'earlybird')
        ->set('password', 'secret-pass-1')->set('password_confirmation', 'secret-pass-1')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertHasNoErrors();

    $user = User::where('email', 'early@b.nl')->first();
    expect($user->is_founding_member)->toBeTrue();
});

it('does not stamp founders once the cohort is full', function () {
    // Fill the cohort exactly.
    User::factory()->count(Stats::FOUNDING_COHORT)->create();

    $cohort = app(FoundingCohort::class);
    expect($cohort->hasFoundingSpot())->toBeFalse()
        ->and($cohort->isRegistrationOpen())->toBeFalse();
});

it('shows the founding-member badge on stats', function () {
    $user = User::factory()->create(['is_founding_member' => true]);
    $stats = app(StatsService::class)->forUser($user);
    $badges = app(BadgeService::class)->earnedFor($stats);

    expect(collect($badges)->pluck('key'))->toContain('founding_member');
});

it('does not award the founding badge to a non-founder', function () {
    $user = User::factory()->create(['is_founding_member' => false]);
    $stats = app(StatsService::class)->forUser($user);
    $badges = app(BadgeService::class)->earnedFor($stats);

    expect(collect($badges)->pluck('key'))->not->toContain('founding_member');
});

it('closes registration and captures a waitlist email when the cohort is full', function () {
    User::factory()->count(Stats::FOUNDING_COHORT)->create();

    // The form is in waitlist mode; submit() is hard-gated.
    Livewire::test(Register::class)
        ->assertSet('waitlisted', false)
        ->set('waitlist_email', 'late@b.nl')
        ->call('joinWaitlist')
        ->assertHasNoErrors()
        ->assertSet('waitlisted', true);

    expect(WaitlistEntry::where('email', 'late@b.nl')->exists())->toBeTrue();
});

it('rejects a duplicate waitlist email', function () {
    User::factory()->count(Stats::FOUNDING_COHORT)->create();
    WaitlistEntry::factory()->create(['email' => 'dupe@b.nl']);

    Livewire::test(Register::class)
        ->set('waitlist_email', 'dupe@b.nl')
        ->call('joinWaitlist')
        ->assertHasErrors(['waitlist_email']);
});

it('blocks account creation via submit once the cohort is full', function () {
    User::factory()->count(Stats::FOUNDING_COHORT)->create();

    Livewire::test(Register::class)
        ->set('email', 'sneaky@b.nl')->set('username', 'sneaky')
        ->set('password', 'secret-pass-1')->set('password_confirmation', 'secret-pass-1')
        ->set('accept_tos', true)
        ->call('submit')
        ->assertForbidden();

    expect(User::where('email', 'sneaky@b.nl')->exists())->toBeFalse();
});

it('keeps registration open when the waitlist feature is off', function () {
    config()->set('cloudmarktplaats.features.waitlist', false);
    User::factory()->count(Stats::FOUNDING_COHORT + 5)->create();

    expect(app(FoundingCohort::class)->isRegistrationOpen())->toBeTrue();
});
