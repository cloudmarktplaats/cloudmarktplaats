<?php

declare(strict_types=1);

use App\Events\Listings\ListingPublished;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;
use Livewire\Livewire;

it('reverses the inviter karma when the invitee is banned', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    event(new ListingPublished(
        Listing::factory()->published()->for($invitee)->create()
    ));
    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);

    Livewire::actingAs($admin)
        ->test(ListUsers::class)
        ->callTableAction('ban', $invitee, data: ['reason' => 'scammer']);

    expect($invitee->refresh()->is_banned)->toBeTrue()
        ->and(app(KarmaService::class)->karmaFor($inviter))->toBe(0);
});

it('reverses the inviter karma when the invitee is banned through the edit form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    event(new ListingPublished(
        Listing::factory()->published()->for($invitee)->create()
    ));
    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $invitee->getKey()])
        ->fillForm(['is_banned' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($invitee->refresh()->is_banned)->toBeTrue()
        ->and(app(KarmaService::class)->karmaFor($inviter))->toBe(0);
});
