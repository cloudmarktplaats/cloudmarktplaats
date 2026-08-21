<?php

declare(strict_types=1);

namespace App\Services\Listings;

use App\Models\Listing;
use App\Services\Storage\PhotoFileEraser;

/**
 * Hard-deletes a listing: the row and the image files behind it.
 *
 * The database side takes care of itself — `listing_photos`, `transactions`
 * and `contact_relay_logs` all hang off `listings` with ON DELETE CASCADE.
 * The files do not; those go through {@see PhotoFileEraser} first.
 */
class ListingRemovalService
{
    public function __construct(private PhotoFileEraser $files) {}

    public function remove(Listing $listing): void
    {
        foreach ($listing->photos as $photo) {
            $this->files->erase($photo->disk, (string) $photo->path);
        }

        // forceDelete, niet delete: {@see Listing} gebruikt SoftDeletes, dus
        // een gewone delete zet alleen `deleted_at` en laat de rij, de
        // `listing_photos` en de foto's op schijf gewoon staan. Van buiten
        // ziet dat er identiek uit — de advertentie is weg uit elke query —
        // terwijl we de gebruiker "definitief verwijderd" beloofd hebben. De
        // ON DELETE CASCADE vuurt pas bij een echte DELETE.
        $listing->forceDelete();
    }
}
