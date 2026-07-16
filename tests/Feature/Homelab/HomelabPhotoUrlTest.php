<?php

declare(strict_types=1);

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;

/**
 * Foto-URLs voor homelabs lopen nu via HomelabPhoto, een spiegel van
 * ListingPhoto. Met de mime-kolom is `original` wél bouwbaar — dat was de hele
 * reden voor deze feature. Het oude contract (photoUrl('original') gooit) is
 * daarmee vervallen; wat blijft is: card/thumb zijn webp, original volgt de
 * bron-mime, en een verzonnen variant is onbouwbaar.
 */
beforeEach(function () {
    Storage::fake('public');
});

it('builds a webp url for card and thumb', function () {
    $photo = HomelabPhoto::factory()->create([
        'disk' => 'local',
        'path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/1/card.webp',
        'mime' => 'image/jpeg',
    ]);

    expect($photo->urlFor('card'))->toContain('/1/card.webp')
        ->and($photo->urlFor('thumb'))->toContain('/1/thumb.webp');
});

it('builds an original url from the stored source mime', function () {
    $photo = HomelabPhoto::factory()->create([
        'disk' => 'local',
        'path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/1/card.webp',
        'mime' => 'image/jpeg',
    ]);

    // De card is webp, maar de original houdt zijn eigen extensie — dat is
    // precies waarvoor de mime-kolom bestaat.
    expect($photo->urlFor('original'))->toContain('/1/original.jpg');
});

it('post photoUrl delegates to the first photo', function () {
    $post = HomelabPost::factory()->create();
    HomelabPhoto::factory()->for($post, 'post')->create([
        'path' => 'homelabs/'.$post->ulid.'/1/card.webp',
        'position' => 0,
        'mime' => 'image/png',
    ]);

    expect($post->photoUrl('card'))->toContain('/1/card.webp')
        ->and($post->photoUrl('original'))->toContain('/1/original.png');
});

it('post photoUrl throws when there is no photo', function () {
    $post = HomelabPost::factory()->create();

    // Kan niet via het formulier, wel via een half mislukte migratie. Een dode
    // URL is erger dan een duidelijke fout.
    expect(fn () => $post->photoUrl('card'))
        ->toThrow(RuntimeException::class);
});
