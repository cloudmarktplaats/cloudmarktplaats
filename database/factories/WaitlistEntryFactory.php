<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WaitlistEntry> */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'invited' => false,
        ];
    }
}
