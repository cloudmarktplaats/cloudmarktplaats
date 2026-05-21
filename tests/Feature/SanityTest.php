<?php

use App\Models\User;

it('boots the application — anonymous lands on marketing home', function () {
    // After the marketing layer, `/` serves pages.home (HTTP 200) for guests
    // and redirects authenticated users to /listings. See routes/web.php.
    $this->get('/')->assertStatus(200);
});

it('boots the application — authenticated user is sent to /listings', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect('/listings');
    $this->get('/listings')->assertStatus(200);
});
