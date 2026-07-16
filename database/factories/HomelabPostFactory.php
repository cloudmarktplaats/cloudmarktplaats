<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPhoto;
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
            'title' => fake()->optional()->sentence(4),
            'body' => fake()->sentences(2, true),
            'feedback_prompt' => null,
            'comments_open' => true,
            'photo_disk' => 'local',
            'photo_path' => 'homelabs/fake/card.webp',
            'status' => 'published',
        ];
    }

    public function removed(): static
    {
        return $this->state(fn () => ['status' => 'removed']);
    }

    /**
     * Een gepubliceerde post heeft in werkelijkheid altijd minstens één foto —
     * het formulier dwingt dat af. Tests die een post renderen hebben die foto
     * dus nodig, sinds photoUrl() naar de photos-relatie delegeert.
     */
    public function withPhoto(): static
    {
        return $this->afterCreating(function (HomelabPost $post): void {
            HomelabPhoto::factory()->for($post, 'post')->create([
                'path' => 'homelabs/'.$post->ulid.'/0/card.webp',
                'position' => 0,
                'mime' => 'image/jpeg',
            ]);
        });
    }
}
