<?php

it('boots the application', function () {
    // `/` redirects to /listings after Phase G; check the redirect
    // chain lands on a 200 page.
    $this->get('/')
        ->assertRedirect('/listings');
    $this->get('/listings')->assertStatus(200);
});
