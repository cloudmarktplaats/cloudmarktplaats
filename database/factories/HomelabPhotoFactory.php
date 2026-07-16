<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPhoto;
use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPhoto> */
class HomelabPhotoFactory extends Factory
{
    protected $model = HomelabPhoto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'homelab_post_id' => HomelabPost::factory(),
            'disk' => 'local',
            'path' => 'homelabs/fake/1/card.webp',
            'width' => 1200,
            'height' => 900,
            'mime' => 'image/jpeg',
            'byte_size' => 123456,
            'position' => 0,
        ];
    }
}
