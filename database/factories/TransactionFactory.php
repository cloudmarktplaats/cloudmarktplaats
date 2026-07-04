<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'seller_user_id' => User::factory(),
            'buyer_user_id' => User::factory(),
            'amount_cents' => 2500,
            'currency' => 'EUR',
            'status' => 'pending',
            'off_platform' => true,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'completed_at' => now()]);
    }
}
