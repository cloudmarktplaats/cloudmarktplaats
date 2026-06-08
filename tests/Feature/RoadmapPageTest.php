<?php

declare(strict_types=1);

it('serves the roadmap page with the three status columns', function () {
    $this->get('/roadmap')
        ->assertOk()
        ->assertSee('Nu live')
        ->assertSee('In aanbouw')
        ->assertSee('Verkend')
        ->assertSee('Contact via relay')
        ->assertSee('Berichten')
        ->assertSee('Web3-escrow')
        ->assertSee('Dit is richting, geen belofte', false);
});

it('names no dates or quarters on the roadmap', function () {
    $html = $this->get('/roadmap')->getContent();

    expect($html)->not->toMatch('/\bQ[1-4]\b/');
});

it('links the roadmap from the footer', function () {
    // The footer ships on every marketing page; the homepage is the
    // simplest guest-reachable carrier.
    $this->get('/')
        ->assertOk()
        ->assertSee(route('roadmap'), false);
});
