<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedSource> */
class FeedSourceFactory extends Factory
{
    protected $model = FeedSource::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'feed_url' => 'https://example.test/'.Str::slug($name).'/feed.xml',
            'homepage_url' => 'https://example.test/'.Str::slug($name),
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
