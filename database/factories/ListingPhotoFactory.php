<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingPhoto>
 */
class ListingPhotoFactory extends Factory
{
    protected $model = ListingPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'disk' => 'local',
            'path' => 'listings/test/1/card.webp',
            'width' => 600,
            'height' => 600,
            'mime' => 'image/webp',
            'byte_size' => 12_345,
            'position' => 1,
        ];
    }
}
