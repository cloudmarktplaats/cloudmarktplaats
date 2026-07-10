<?php

declare(strict_types=1);

use App\Models\User;

it('shows the account menu with a my-listings link to authenticated users', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Mijn advertenties')
        ->assertSee(route('listings.mine'), false)
        ->assertSee(route('profile.security'), false);
});

it('does not expose the account menu to guests', function () {
    $this->get(route('listings.index'))
        ->assertOk()
        ->assertDontSee(route('listings.mine'), false);
});
