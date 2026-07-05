<?php

declare(strict_types=1);

use App\Livewire\LaunchStats;
use App\Models\Listing;
use App\Models\User;
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
