<?php

declare(strict_types=1);

use App\Livewire\Listings\Mine;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Een verkoper kon zijn eigen advertentie niet weghalen. Niet als concept,
 * niet tijdens moderatie, niet na goedkeuring. `archived` bestond wel in de
 * state machine maar had nul aanroepers, en `ListingPolicy::delete()` was
 * staff-only. Drie leden meldden het (issues #9 en #10) en één van hen zegde
 * er zijn account om op — terwijl het privacybeleid al beloofde dat je een
 * advertentie bewaart "tot je hem verwijdert".
 */

it('takes a published listing offline from Mijn advertenties', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->published()->create();

    Livewire::actingAs($user)
        ->test(Mine::class)
        ->call('archive', $listing->id);

    expect($listing->fresh()->state)->toBe('archived');
});

// Precies Robs geval: ingediend, nog niet gemodereerd, spul inmiddels elders
// verkocht. Juist in die wachttijd was er geen uitweg.
it('takes a listing offline while it is still waiting for moderation', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create(['state' => 'pending_review']);

    Livewire::actingAs($user)
        ->test(Mine::class)
        ->call('archive', $listing->id);

    expect($listing->fresh()->state)->toBe('archived');
});

it('lets the seller put an archived listing back as a draft', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create(['state' => 'archived']);

    Livewire::actingAs($user)
        ->test(Mine::class)
        ->call('restore', $listing->id);

    expect($listing->fresh()->state)->toBe('draft');
});

it('refuses to touch a listing that belongs to someone else', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $listing = Listing::factory()->for($owner)->published()->create();

    Livewire::actingAs($intruder)
        ->test(Mine::class)
        ->call('archive', $listing->id)
        ->assertForbidden();

    expect($listing->fresh()->state)->toBe('published');
});

// Offline halen laat de gegevens staan; verwijderen hoort ze echt weg te
// halen — anders belooft het privacybeleid iets wat de code niet doet.
it('permanently deletes a listing including its photo files', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->published()->create();
    $photo = ListingPhoto::factory()->for($listing)->create([
        'disk' => 'local',
        'mime' => 'image/jpeg',
        'path' => "listings/{$listing->ulid}/1/card.webp",
    ]);

    $dir = "listings/{$listing->ulid}/{$photo->id}";
    Storage::disk('public')->put("{$dir}/original.jpg", 'x');
    Storage::disk('public')->put("{$dir}/card.webp", 'x');
    Storage::disk('public')->put("{$dir}/thumb.webp", 'x');
    $photo->forceFill(['path' => "{$dir}/card.webp"])->save();

    Livewire::actingAs($user)
        ->test(Mine::class)
        ->call('confirmDelete', $listing->id)
        ->call('destroyListing', $listing->id);

    // withTrashed(): Listing gebruikt SoftDeletes, dus zonder deze regel slaagt
    // deze test ook als de rij er nog gewoon staat met een `deleted_at`.
    expect(Listing::withTrashed()->find($listing->id))->toBeNull()
        ->and(ListingPhoto::query()->find($photo->id))->toBeNull();

    Storage::disk('public')->assertMissing("{$dir}/original.jpg");
    Storage::disk('public')->assertMissing("{$dir}/card.webp");
    Storage::disk('public')->assertMissing("{$dir}/thumb.webp");
});

// Verwijderen is onomkeerbaar, dus het mag niet op één misklik gebeuren.
it('does not delete without a confirmation step', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->published()->create();

    Livewire::actingAs($user)
        ->test(Mine::class)
        ->call('destroyListing', $listing->id);

    expect(Listing::query()->find($listing->id))->not->toBeNull();
});

it('refuses to delete a listing that belongs to someone else', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $listing = Listing::factory()->for($owner)->published()->create();

    Livewire::actingAs($intruder)
        ->test(Mine::class)
        ->call('confirmDelete', $listing->id)
        ->call('destroyListing', $listing->id)
        ->assertForbidden();

    expect(Listing::query()->find($listing->id))->not->toBeNull();
});

it('shows the offline and delete buttons on Mijn advertenties', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->published()->create();

    $this->actingAs($user)
        ->get('/mijn-advertenties')
        ->assertOk()
        ->assertSee('Offline halen')
        ->assertSee('Verwijderen');
});
