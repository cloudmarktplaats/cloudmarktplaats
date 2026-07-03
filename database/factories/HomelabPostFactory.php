<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPost> */
class HomelabPostFactory extends Factory
{
    protected $model = HomelabPost::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentences(2, true),
            'photo_disk' => 'local',
            'photo_path' => 'homelabs/fake/card.webp',
            'status' => 'published',
        ];
    }

    public function removed(): static
    {
        return $this->state(fn () => ['status' => 'removed']);
    }
}
