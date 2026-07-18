<?php

declare(strict_types=1);

it('sends an unauthenticated visitor from the panel to the app login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('no longer exposes a standalone admin login route', function () {
    $this->get('/admin/login')->assertNotFound();
});
