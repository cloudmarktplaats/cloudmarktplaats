<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'condition' => 'used',
            'price_cents' => fake()->numberBetween(500, 500_000),
            'is_trade_allowed' => false,
            'region_postcode' => (string) fake()->numberBetween(1000, 9999),
            'shipping_options' => ['pickup' => true, 'post' => false],
            'state' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['state' => 'published', 'published_at' => now()]);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['state' => 'sold', 'published_at' => now()]);
    }
}
