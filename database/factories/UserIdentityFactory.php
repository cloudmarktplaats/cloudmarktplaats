<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserIdentity>
 */
class UserIdentityFactory extends Factory
{
    protected $model = UserIdentity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'password',
            'provider_uid' => 'password',
        ];
    }

    public function password(): static
    {
        return $this->state(fn () => ['provider' => 'password', 'provider_uid' => 'password']);
    }

    public function github(string $uid): static
    {
        return $this->state(fn () => ['provider' => 'oauth_github', 'provider_uid' => $uid]);
    }

    public function gitlab(string $uid): static
    {
        return $this->state(fn () => ['provider' => 'oauth_gitlab', 'provider_uid' => $uid]);
    }

    public function siwe(string $address): static
    {
        return $this->state(fn () => ['provider' => 'siwe', 'provider_uid' => strtolower($address)]);
    }
}
