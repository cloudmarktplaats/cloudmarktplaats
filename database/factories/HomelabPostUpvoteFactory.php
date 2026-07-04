<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HomelabPost;
use App\Models\HomelabPostUpvote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HomelabPostUpvote> */
class HomelabPostUpvoteFactory extends Factory
{
    protected $model = HomelabPostUpvote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'homelab_post_id' => HomelabPost::factory(),
        ];
    }
}
