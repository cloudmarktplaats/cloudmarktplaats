<?php

declare(strict_types=1);

use App\Livewire\Homelab\Feed;
use App\Models\HomelabPost;
use App\Models\User;
use Livewire\Livewire;

it('never leaks the poster identity in the feed HTML', function () {
    $user = User::factory()->create([
        'username' => 'rackmaster9000',
        'display_name' => 'Rack Master',
    ]);
    HomelabPost::factory()->for($user)->withPhoto()->create(['body' => 'stealth lab']);

    $this->get('/homelabs')
        ->assertOk()
        ->assertSee('stealth lab')
        ->assertDontSee('rackmaster9000')
        ->assertDontSee('Rack Master');
});

it('lets the owner remove their own post', function () {
    $user = User::factory()->create();
    $post = HomelabPost::factory()->for($user)->withPhoto()->create();

    Livewire::actingAs($user)
        ->test(Feed::class)
        ->call('deleteOwn', $post->ulid);

    expect($post->refresh()->status)->toBe('removed');
});

it('forbids removing someone elses post', function () {
    $post = HomelabPost::factory()->withPhoto()->create();
    $other = User::factory()->create();

    Livewire::actingAs($other)
        ->test(Feed::class)
        ->call('deleteOwn', $post->ulid)
        ->assertForbidden();

    expect($post->refresh()->status)->toBe('published');
});
