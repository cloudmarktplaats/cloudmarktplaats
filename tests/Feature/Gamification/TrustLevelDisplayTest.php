<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Livewire\Profile\Stats;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('shows the user their own trust level on the stats page', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);

    // 'Vertrouwensniveau' is emitted only by the trust tile, so this
    // genuinely pins the tile's presence (unlike 'Lid', which collides
    // with the "Lid sinds" member-since tile).
    Livewire::actingAs($u)->test(Stats::class)->assertOk()->assertSee('Vertrouwensniveau');
});

it('hides the trust tile when FEATURE_TRUST is off', function () {
    config()->set('cloudmarktplaats.features.trust', false);
    $u = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($u)->test(Stats::class)->assertOk()->assertDontSee('Vertrouwensniveau');
});

it('shows a trust column on the Filament users table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $veteran = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(40)]);
    Listing::factory()->sold()->for($veteran)->count(5)->create();

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->assertTableColumnExists('trust')
        ->assertTableColumnStateSet('trust', 'Veteraan', record: $veteran);
});
