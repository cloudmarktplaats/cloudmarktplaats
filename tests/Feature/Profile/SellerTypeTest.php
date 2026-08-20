<?php

declare(strict_types=1);

use App\Livewire\Profile\SellerType;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

it('stores the business details when someone switches to business', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SellerType::class)
        ->set('isBusiness', true)
        ->set('businessName', 'Zicht ICT B.V.')
        ->set('businessRegistration', '12345678')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->seller_type)->toBe('business')
        ->and($user->business_name)->toBe('Zicht ICT B.V.')
        ->and($user->business_registration)->toBe('12345678');
});

// De informatieplicht draait om identiteit; zonder naam en KvK heeft het label
// geen betekenis en zou het juist misleiden.
it('refuses business without a name and a registration number', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SellerType::class)
        ->set('isBusiness', true)
        ->call('save')
        ->assertHasErrors(['businessName', 'businessRegistration']);

    expect($user->refresh()->seller_type)->toBe('private');
});

it('clears the business details when someone switches back', function () {
    $user = User::factory()->create([
        'seller_type' => 'business',
        'business_name' => 'Zicht ICT B.V.',
        'business_registration' => '12345678',
    ]);

    $this->actingAs($user);

    Livewire::test(SellerType::class)
        ->set('isBusiness', false)
        ->call('save');

    $user->refresh();
    expect($user->seller_type)->toBe('private')
        ->and($user->business_name)->toBeNull();
});

it('tells the buyer on the listing page that this is a business', function () {
    $seller = User::factory()->create([
        'seller_type' => 'business',
        'business_name' => 'Zicht ICT B.V.',
        'business_registration' => '12345678',
    ]);
    $listing = Listing::factory()->for($seller)->published()->create();

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertSee('Zakelijke verkoper')
        ->assertSee('Zicht ICT B.V.')
        ->assertSee('12345678')
        ->assertSee('veertien dagen bedenktijd');
});

// Afwezigheid van het label is géén claim dat iemand particulier is.
it('says nothing at all for a private seller', function () {
    $listing = Listing::factory()->published()->create();

    $this->get("/listings/{$listing->ulid}-{$listing->slug}")
        ->assertOk()
        ->assertDontSee('Zakelijke verkoper');
});
