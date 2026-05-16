<?php

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user with multiple identities', function () {
    $user = User::factory()->create(['email' => 'a@b.nl']);
    UserIdentity::factory()->password()->for($user)->create();
    UserIdentity::factory()->github('12345')->for($user)->create();

    expect($user->identities)->toHaveCount(2);
});

it('blocks duplicate provider+uid', function () {
    UserIdentity::factory()->github('12345')->create();

    expect(fn () => UserIdentity::factory()->github('12345')->create())
        ->toThrow(QueryException::class);
});
