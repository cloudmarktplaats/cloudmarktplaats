<?php

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

/*
 * NotifyPhotoBugDrafts, RemindDraftListings en SendListingPublishedMail
 * slaan een owner zonder e-mailadres over via `! $user instanceof User`
 * alleen — geen aparte `$user->email === null` meer, want die kon nooit
 * waar zijn. Een kale insert (niet via het model/de factory, die zelf al
 * een e-mailadres eisen) bewijst dat op databaseniveau, niet alleen via
 * schema-metadata. Zakt deze test, dan raken die drie call sites hun
 * dekking kwijt en moet de skip-check daar terugkomen.
 */
it('enforces email is never null at the database level', function () {
    expect(fn () => DB::table('users')->insert([
        'ulid' => strtolower((string) Str::ulid()),
        'email' => null,
        'username' => 'geeneemail',
        'display_name' => 'Geen E-mail',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
