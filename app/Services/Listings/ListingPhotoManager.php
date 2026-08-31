<?php

declare(strict_types=1);

namespace App\Services\Listings;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\Storage\PhotoFileEraser;

/**
 * Verwijdert en herordent de foto's van één advertentie.
 *
 * De wizard kende alleen toevoegen, dus een verkeerd gezette foto kostte je de
 * hele advertentie. Verwijderen gaat via {@see PhotoFileEraser}, die per map
 * wist: de extensie van `original.{ext}` komt uit de `mime`-kolom en die klopt
 * op de oudste rijen niet met wat er op schijf staat. Stel hier dus nooit zelf
 * een bestandsnaam samen.
 */
class ListingPhotoManager
{
    public function __construct(private PhotoFileEraser $files) {}

    public function delete(Listing $listing, ListingPhoto $photo): void
    {
        $this->files->erase($photo->disk, (string) $photo->path);
        $photo->delete();

        $this->renumber($listing);
    }

    /** Wisselt de foto met zijn buurman; aan de rand gebeurt er niets. */
    public function move(Listing $listing, ListingPhoto $photo, string $direction): void
    {
        $up = $direction === 'up';

        $neighbour = $listing->photos()
            ->where('position', $up ? '<' : '>', $photo->position)
            ->reorder('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbour !== null) {
            $this->swap($photo, $neighbour);
        }
    }

    /**
     * Ruilt twee posities om via 0.
     *
     * `(listing_id, position)` is uniek, dus de twee rijen kunnen niet even
     * tegelijk dezelfde plek dragen. Posities lopen vanaf 1 en de kolom is een
     * unsigned tinyint, dus 0 is de enige veilige tussenstap.
     */
    private function swap(ListingPhoto $photo, ListingPhoto $neighbour): void
    {
        $from = (int) $photo->position;
        $to = (int) $neighbour->position;

        $photo->forceFill(['position' => 0])->save();
        $neighbour->forceFill(['position' => $from])->save();
        $photo->forceFill(['position' => $to])->save();
    }

    /**
     * Sluit de gaten in `position`.
     *
     * Zonder dit bepaalt een gat waar de volgende upload landt, want die telt
     * verder vanaf het maximum. De volgorde hoort te kloppen zonder dat de
     * verkoper de nummers kent.
     */
    private function renumber(Listing $listing): void
    {
        foreach ($listing->photos()->get() as $index => $photo) {
            $photo->forceFill(['position' => $index + 1])->save();
        }
    }
}
