<?php

declare(strict_types=1);

use App\Livewire\LaunchStats;
use App\Models\InviteCode;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\StatsService as Stats;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('shows real founding-member and listing counts on the homepage strip', function () {
    Cache::flush();
    $users = User::factory()->count(7)->create();
    // Assign the listings to an existing user so the Listing factory doesn't
    // spawn extra users and inflate the founding-member count.
    Listing::factory()->count(3)->create(['state' => 'published', 'user_id' => $users->first()->id]);

    Livewire::test(LaunchStats::class)
        ->assertSee('7')       // founding members
        ->assertSee('/ 100')   // cohort anchor
        ->assertSee('3');      // listings live
});

it('excludes banned users from the founding-member count', function () {
    Cache::flush();
    User::factory()->count(4)->create();
    User::factory()->create(['is_banned' => true]);

    // 4 counted, banned one excluded → 96 spots left.
    Livewire::test(LaunchStats::class)->assertSee('96');
});

it('drops the scarcity anchor and shows invites once the cohort is closed', function () {
    Cache::flush();
    $founders = User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);
    InviteCode::factory()->count(2)->create(['inviter_user_id' => $founders->first()->id]);

    Livewire::test(LaunchStats::class)
        ->assertViewHas('full', true)
        ->assertViewHas('invitesOpen', 2)
        ->assertSee('uitnodigingen open')
        ->assertSee('Nieuwe leden zijn nog steeds welkom')
        ->assertDontSee('plekken vrij')
        ->assertDontSee('/ 100')
        ->assertDontSee('wachtlijst');
});

it('does not flip back to scarcity when a founder leaves', function () {
    Cache::flush();
    $founders = User::factory()->count(Stats::FOUNDING_COHORT)->create(['is_founding_member' => true]);

    // 99 leden, maar 100 badges gestempeld: de cohort blijft dicht.
    $founders->first()->delete();

    Livewire::test(LaunchStats::class)
        ->assertViewHas('full', true)
        ->assertDontSee('plekken vrij')
        ->assertDontSee('/ 100');
});
