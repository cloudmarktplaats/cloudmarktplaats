<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KarmaEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KarmaEvent> */
class KarmaEventFactory extends Factory
{
    protected $model = KarmaEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'invite_activation',
            'points' => 10,
            'source_type' => null,
            'source_id' => null,
        ];
    }
}
