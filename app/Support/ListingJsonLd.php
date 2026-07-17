<?php

declare(strict_types=1);

namespace App\Support;

use App\Livewire\Listings\Detail;
use App\Models\Listing;

/**
 * Bouwt schema.org Product+Offer JSON-LD voor een advertentie.
 *
 * Deze klasse kent de published-gate NIET — de caller
 * ({@see Detail::render()}) roept dit alleen aan
 * binnen de published-tak, zodat een niet-publieke advertentie geen
 * prijs/titel/foto via structured data lekt.
 */
class ListingJsonLd
{
    public function toJson(Listing $listing): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $listing->title,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($listing->price_cents / 100, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'itemCondition' => $this->itemCondition($listing->condition),
                'url' => route('listings.detail', [
                    'ulid' => $listing->ulid,
                    'slug' => $listing->slug,
                ]),
            ],
        ];

        $description = trim((string) $listing->description);

        if ($description !== '') {
            $data['description'] = $description;
        }

        $images = $listing->photos
            ->map(fn ($photo) => $photo->urlFor('original'))
            ->all();

        if ($images !== []) {
            $data['image'] = array_values($images);
        }

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    /**
     * `condition`-enum → schema.org itemCondition-URL. De default-arm vangt
     * een toekomstige enum-uitbreiding af zodat de detail-render niet crasht
     * op een UnhandledMatchError.
     */
    private function itemCondition(string $condition): string
    {
        return match ($condition) {
            'new' => 'https://schema.org/NewCondition',
            'used' => 'https://schema.org/UsedCondition',
            'defective', 'for_parts' => 'https://schema.org/DamagedCondition',
            default => 'https://schema.org/UsedCondition',
        };
    }
}
