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
            // Placeholder; finalized in afterMaking() below so the value
            // reflects the actual user id (avoids the unique-constraint
            // collision that a fixed `password` token would cause).
            'provider_uid' => 'password',
        ];
    }

    /**
     * Ensure password identities get a unique provider_uid derived from the
     * owning user's primary key. This keeps the (provider, provider_uid)
     * unique index satisfied across any number of users.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (UserIdentity $identity): void {
            if ($identity->provider === 'password') {
                $identity->provider_uid = (string) $identity->user_id;
            }
        })->afterCreating(function (UserIdentity $identity): void {
            if ($identity->provider === 'password' && $identity->provider_uid !== (string) $identity->user_id) {
                $identity->provider_uid = (string) $identity->user_id;
                $identity->save();
            }
        });
    }

    public function password(): static
    {
        return $this->state(fn () => ['provider' => 'password']);
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
