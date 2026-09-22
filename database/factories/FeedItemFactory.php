<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedItem> */
class FeedItemFactory extends Factory
{
    protected $model = FeedItem::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'feed_source_id' => FeedSource::factory(),
            'guid' => 'urn:'.Str::uuid()->toString(),
            'title' => $title,
            'url' => 'https://example.test/'.Str::slug($title),
            'summary' => $this->faker->sentence(12),
            'published_at' => now()->subHours($this->faker->numberBetween(1, 240)),
            'fetched_at' => now(),
        ];
    }
}
