<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InviteCode> */
class InviteCodeFactory extends Factory
{
    protected $model = InviteCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inviter_user_id' => User::factory(),
            'invitee_user_id' => null,
            'used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'invitee_user_id' => User::factory(),
            'used_at' => now(),
        ]);
    }
}
