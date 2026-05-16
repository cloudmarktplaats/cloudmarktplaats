<?php

it('returns ok for all components', function () {
    $response = $this->getJson('/healthz');
    $response->assertOk()
        ->assertJsonStructure(['db', 'redis', 'storage', 'version']);
    expect($response->json('db'))->toBe('ok');
    expect($response->json('redis'))->toBe('ok');
    expect($response->json('storage'))->toBe('ok');
});
