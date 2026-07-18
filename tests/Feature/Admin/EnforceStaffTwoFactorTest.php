<?php

declare(strict_types=1);

use App\Models\User;

it('redirects a staff member without confirmed 2FA to the setup page', function () {
    $admin = User::factory()->admin()->create(); // two_factor_confirmed_at is null

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(route('profile.security.2fa'));
});

it('treats a moderator the same as an admin', function () {
    $moderator = User::factory()->moderator()->create();

    $this->actingAs($moderator)
        ->get('/admin')
        ->assertRedirect(route('profile.security.2fa'));
});

it('does not bounce a staff member who has confirmed 2FA', function () {
    $admin = User::factory()->admin()->create();
    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

    $response = $this->actingAs($admin)->get('/admin');

    // Deze middleware mag hem niet naar de instelpagina sturen. Ander
    // panel-gedrag (dashboard-render) valt buiten deze taak, dus we toetsen
    // precies de contract-uitkomst van déze middleware.
    expect($response->headers->get('Location'))->not->toBe(route('profile.security.2fa'));
});

it('never redirects a non-staff user to 2FA setup — the role gate wins first', function () {
    $user = User::factory()->create(); // role: user

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden(); // 403 uit role:admin,moderator, vóór onze middleware
});
