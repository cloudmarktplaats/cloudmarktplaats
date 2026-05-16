<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reportable_type' => 'listing',
            'reportable_id' => Listing::factory(),
            'reporter_user_id' => User::factory(),
            'reason' => 'spam',
            'details' => fake()->sentence(),
            'status' => 'open',
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => 'open']);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'resolved']);
    }
}
