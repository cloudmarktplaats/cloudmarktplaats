<?php

declare(strict_types=1);

use App\Models\HomelabPost;
use Illuminate\Support\Facades\Storage;

/**
 * photoUrl() may only hand out URLs it can actually build.
 *
 * The old version derived the extension from `photo_path` — but that column
 * always holds the *card* path (`…/card.webp`, see StoreHomelabPhotoJob), so
 * pathinfo() always returned "webp" and `photoUrl('original')` produced
 * `original.webp` for a file stored as `original.jpg`: a 404. Unlike
 * ListingPhoto, there is no `mime` column to recover the source extension
 * from, so an 'original' URL cannot be built at all — and pretending
 * otherwise is what made the identical ListingPhoto bug sit latent until
 * something finally used it.
 *
 * The original file stays on disk as an archive; it is simply not addressable.
 */
beforeEach(function () {
    Storage::fake('public');
});

it('builds webp urls for the addressable variants', function () {
    $post = HomelabPost::factory()->create([
        'photo_disk' => 'local',
        'photo_path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/card.webp',
    ]);

    expect($post->photoUrl('card'))->toContain('homelabs/01KWWEFB83KTBMRAHX24BNTVFE/card.webp')
        ->and($post->photoUrl())->toContain('card.webp');
});

it('refuses to invent an original url it cannot build', function () {
    $post = HomelabPost::factory()->create([
        'photo_disk' => 'local',
        'photo_path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/card.webp',
    ]);

    // The source mime is not stored, so the real extension (.jpg/.png/.webp)
    // is unknowable. Returning ".../original.webp" would be a guess that 404s
    // most of the time — fail loudly instead of silently pointing at nothing.
    expect(fn () => $post->photoUrl('original'))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an unknown variant rather than building a dead url', function () {
    $post = HomelabPost::factory()->create([
        'photo_disk' => 'local',
        'photo_path' => 'homelabs/01KWWEFB83KTBMRAHX24BNTVFE/card.webp',
    ]);

    expect(fn () => $post->photoUrl('verzonnen'))
        ->toThrow(InvalidArgumentException::class);
});
