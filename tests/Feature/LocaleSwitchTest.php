<?php

declare(strict_types=1);

it('defaults to Dutch and switches to English via the switcher', function () {
    // Dutch by default: the homepage shows a Dutch nav label.
    $this->get('/')->assertOk()->assertSee('Advertentie plaatsen');

    // Switch to English, then the homepage nav shows the English label.
    $this->get('/taal/en');
    $this->get('/')->assertOk()->assertSee('Post a listing')->assertDontSee('Advertentie plaatsen');

    // Switch back to Dutch.
    $this->get('/taal/nl');
    $this->get('/')->assertOk()->assertSee('Advertentie plaatsen');
});

it('ignores an unsupported locale', function () {
    $this->get('/taal/fr')->assertNotFound();
});
