<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MailSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MailSubscription> */
class MailSubscriptionFactory extends Factory
{
    protected $model = MailSubscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'user_id' => null,
            'wants_offers' => true,
            'wants_updates' => false,
            'categories' => ['networking'],
            'confirm_token' => null,
            'confirmed_at' => now(),
            'unsubscribe_token' => Str::random(48),
            'consent_text' => 'Ja, mail mij nieuw aanbod in deze categorieen.',
            'consent_given_at' => now(),
            'consent_source' => 'formulier',
        ];
    }

    public function unconfirmed(): static
    {
        return $this->state(fn () => [
            'confirmed_at' => null,
            'confirm_token' => Str::random(48),
        ]);
    }
}
