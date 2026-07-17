<?php

declare(strict_types=1);

it('emits Organization and WebSite JSON-LD on the guest homepage', function () {
    // Ingelogde gebruikers worden naar /listings geleid; de marketing-home
    // met schema is de gast-variant.
    $this->get('/')
        ->assertOk()
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"WebSite"', false);
});
