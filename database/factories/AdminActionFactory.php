<?php

namespace Database\Factories;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAction>
 */
class AdminActionFactory extends Factory
{
    protected $model = AdminAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'action' => 'listing.reject',
            'target_type' => 'listing',
            'target_id' => 1,
            'meta' => ['reason' => 'duplicate'],
            'ip_hash' => str_repeat('a', 64),
            'created_at' => now(),
        ];
    }
}
