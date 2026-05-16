<?php

declare(strict_types=1);

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

/**
 * Build a Mockery mock of a Socialite Two\User and bind it to the
 * Socialite::driver($provider)->user() chain so that test code may
 * exercise OAuthController::callback() without hitting the network.
 */
function fakeSocialiteUser(
    string $provider,
    string $uid,
    ?string $email,
    string $name = 'Test',
    ?string $nickname = null,
): void {
    /** @var MockInterface&SocialiteUser $u */
    $u = Mockery::mock(SocialiteUser::class);
    $u->shouldReceive('getId')->andReturn($uid);
    $u->shouldReceive('getEmail')->andReturn($email);
    $u->shouldReceive('getName')->andReturn($name);
    $u->shouldReceive('getNickname')->andReturn($nickname ?? strtolower($name));

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($u);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}
