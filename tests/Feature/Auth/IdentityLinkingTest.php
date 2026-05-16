<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\IdentityService;
use App\Services\Auth\LastIdentityException;

it('refuses to unlink the last identity', function (): void {
    $u = User::factory()->create();
    $only = UserIdentity::factory()->password()->for($u)->create();

    expect(app(IdentityService::class)->canUnlink($u, $only))->toBeFalse();
    expect(fn () => app(IdentityService::class)->unlink($u, $only))
        ->toThrow(LastIdentityException::class);
});

it('allows unlinking when more than one identity exists', function (): void {
    $u = User::factory()->create();
    $pwd = UserIdentity::factory()->password()->for($u)->create();
    UserIdentity::factory()->github('1')->for($u)->create();

    expect(app(IdentityService::class)->canUnlink($u, $pwd))->toBeTrue();
    app(IdentityService::class)->unlink($u, $pwd);
    expect($u->identities()->count())->toBe(1);
});
