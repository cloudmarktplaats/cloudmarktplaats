<?php

it('boots the application', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});
