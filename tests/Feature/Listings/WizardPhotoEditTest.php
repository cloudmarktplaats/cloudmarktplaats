<?php

declare(strict_types=1);

use App\Livewire\Listings\Wizard;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * De wizard kende alleen toevoegen: `position` liep op vanaf het maximum en
 * daar hield het op. Wie een foto verkeerd om zette of de verkeerde koos, kon
 * hem nergens meer weghalen en moest de hele advertentie opnieuw plaatsen.
 * Ramon Fincken meldde dat op 31-08: "ik had er nu een fout en dan moet ik een
 * nieuwe ad plaatsen."
 */

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

/** Maakt een advertentie met $count foto's op posities 1..$count. */
function listingWithPhotos(User $user, int $count, string $state = 'draft'): Listing
{
    $listing = Listing::factory()->for($user)->create(['state' => $state]);

    for ($i = 1; $i <= $count; $i++) {
        ListingPhoto::factory()->for($listing)->create([
            'position' => $i,
            'disk' => 'local',
            'path' => "listings/{$listing->ulid}/foto{$i}/card.webp",
        ]);
    }

    return $listing;
}

it('deletes a photo from the listing', function () {
    $listing = listingWithPhotos($this->user, 2);
    $doomed = $listing->photos()->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('deletePhoto', $doomed->id);

    expect(ListingPhoto::find($doomed->id))->toBeNull()
        ->and($listing->photos()->count())->toBe(1);
});

/*
 * Wist per map, nooit op een samengestelde bestandsnaam: de extensie van
 * `original.{ext}` komt uit de `mime`-kolom en die klopt op de oudste rijen
 * niet met wat er op schijf staat. Bij de eerste echte verwijdering op
 * productie bleef daardoor de foto van een verwijderd lid online staan.
 */
it('erases the files behind a deleted photo', function () {
    $listing = listingWithPhotos($this->user, 2);
    $doomed = $listing->photos()->first();
    $map = dirname((string) $doomed->path);

    Storage::disk('public')->put($map.'/card.webp', 'x');
    Storage::disk('public')->put($map.'/original.jpg', 'x');

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('deletePhoto', $doomed->id);

    expect(Storage::disk('public')->exists($map.'/card.webp'))->toBeFalse()
        ->and(Storage::disk('public')->exists($map.'/original.jpg'))->toBeFalse();
});

/*
 * Zonder hernummeren loopt `position` vol met gaten, en dan bepaalt het gat
 * of de volgende upload vóór of na een bestaande foto landt. De volgorde moet
 * blijven kloppen zonder dat iemand de nummers kent.
 */
it('renumbers the remaining photos so there are no gaps', function () {
    $listing = listingWithPhotos($this->user, 3);
    $middle = $listing->photos()->where('position', 2)->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('deletePhoto', $middle->id);

    expect($listing->photos()->pluck('position')->all())->toBe([1, 2]);
});

it('moves a photo up in the order', function () {
    $listing = listingWithPhotos($this->user, 3);
    $second = $listing->photos()->where('position', 2)->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('movePhoto', $second->id, 'up');

    expect($second->fresh()->position)->toBe(1)
        ->and($listing->photos()->first()->id)->toBe($second->id);
});

it('moves a photo down in the order', function () {
    $listing = listingWithPhotos($this->user, 3);
    $second = $listing->photos()->where('position', 2)->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('movePhoto', $second->id, 'down');

    expect($second->fresh()->position)->toBe(3);
});

it('leaves the order alone when the first photo is moved up', function () {
    $listing = listingWithPhotos($this->user, 3);
    $first = $listing->photos()->where('position', 1)->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('movePhoto', $first->id, 'up');

    expect($listing->photos()->pluck('position')->all())->toBe([1, 2, 3])
        ->and($first->fresh()->position)->toBe(1);
});

/*
 * Een gepubliceerde advertentie zonder foto is precies de fotobug die in juli
 * zes dagen onzichtbaar bleef. Verwijderen mag dus niet de laatste foto van
 * iets dat live staat weghalen; offline halen kan wel, en dat staat in de
 * melding.
 */
it('refuses to delete the last photo of a published listing', function () {
    $listing = listingWithPhotos($this->user, 1, 'published');
    $only = $listing->photos()->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('deletePhoto', $only->id)
        ->assertHasErrors('photos');

    expect($listing->photos()->count())->toBe(1);
});

it('lets the seller delete the last photo of a draft', function () {
    $listing = listingWithPhotos($this->user, 1);
    $only = $listing->photos()->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('deletePhoto', $only->id);

    expect($listing->photos()->count())->toBe(0);
});

/*
 * De id komt uit de browser, dus hij is niet te vertrouwen. Zonder deze
 * controle wist een willekeurig lid de foto's van een ander.
 */
it('refuses to delete a photo that belongs to another listing', function () {
    $mine = listingWithPhotos($this->user, 2);
    $theirs = listingWithPhotos(User::factory()->create(), 2);
    $target = $theirs->photos()->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $mine])
        ->call('deletePhoto', $target->id)
        ->assertStatus(404);

    expect(ListingPhoto::find($target->id))->not->toBeNull();
});

/*
 * De knoppen zijn het halve punt: de methodes bestonden hierboven al voordat
 * er iets te klikken viel, en Ramon kon zijn foto alleen niet weghalen omdat
 * de wizard hem nergens toonde.
 */
it('shows the existing photos with their controls on the photo step', function () {
    $listing = listingWithPhotos($this->user, 2);

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->set('step', 3)
        ->assertSeeHtml('wire:click="deletePhoto('.$listing->photos()->first()->id.')"')
        ->assertSeeHtml('movePhoto');
});

it('refuses to reorder with a direction it does not know', function () {
    $listing = listingWithPhotos($this->user, 2);
    $photo = $listing->photos()->first();

    Livewire::actingAs($this->user)
        ->test(Wizard::class, ['listing' => $listing])
        ->call('movePhoto', $photo->id, 'sideways')
        ->assertStatus(400);

    expect($listing->photos()->pluck('position')->all())->toBe([1, 2]);
});
