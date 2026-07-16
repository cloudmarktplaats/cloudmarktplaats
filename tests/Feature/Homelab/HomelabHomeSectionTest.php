<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use App\Models\User;

it('shows the latest homelab posts on the homepage', function () {
    HomelabPost::factory()->withPhoto()->create(['body' => 'thuisserver hoekje']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Uit de homelabs')
        ->assertSee('thuisserver hoekje');
});

it('hides the section entirely when there are no posts', function () {
    $this->get('/')->assertOk()->assertDontSee('Uit de homelabs');
});

it('hides the section when the flag is off', function () {
    config()->set('cloudmarktplaats.features.homelab_feed', false);
    HomelabPost::factory()->withPhoto()->create();

    $this->get('/')->assertOk()->assertDontSee('Uit de homelabs');
});

it('does not leak identity on the homepage either', function () {
    $user = User::factory()->create(['username' => 'rackmaster9000']);
    HomelabPost::factory()->for($user)->withPhoto()->create();

    $this->get('/')->assertOk()->assertDontSee('rackmaster9000');
});
