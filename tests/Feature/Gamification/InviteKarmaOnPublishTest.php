<?php

declare(strict_types=1);

use App\Events\Listings\ListingPublished;
use App\Models\KarmaEvent;
use App\Models\Listing;
use App\Models\User;
use App\Services\Gamification\KarmaService;

it('awards the inviter karma on the invitees first published listing', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    $listing = Listing::factory()->published()->for($invitee)->create();

    event(new ListingPublished($listing));

    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);
});

it('does not award on a second listing or for an uninvited user', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create(['invited_by' => $inviter->id]);
    $first = Listing::factory()->published()->for($invitee)->create();
    $second = Listing::factory()->published()->for($invitee)->create();

    event(new ListingPublished($first));
    event(new ListingPublished($first)); // replay — still idempotent
    event(new ListingPublished($second));

    expect(app(KarmaService::class)->karmaFor($inviter))->toBe(10);

    $loner = User::factory()->create(['invited_by' => null]);
    event(new ListingPublished(Listing::factory()->published()->for($loner)->create()));
    expect(KarmaEvent::query()->count())->toBe(1);
});
