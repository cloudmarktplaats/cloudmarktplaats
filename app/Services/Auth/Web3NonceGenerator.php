<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuthNonce;
use Illuminate\Support\Str;

/**
 * Issues and consumes single-use SIWE nonces.
 *
 * Nonces are persisted in `auth_nonces` with a 5-minute TTL. Each row is
 * bound to a lower-cased Ethereum address so the verify endpoint can only
 * consume a nonce that was issued for the same wallet that signed the
 * message. Consumption marks `used_at` to defeat replay.
 */
class Web3NonceGenerator
{
    public function issue(string $address): AuthNonce
    {
        return AuthNonce::create([
            'nonce' => Str::random(32),
            'address' => strtolower($address),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function consume(string $nonce, string $address): ?AuthNonce
    {
        $row = AuthNonce::where('nonce', $nonce)
            ->where('address', strtolower($address))
            ->first();

        if ($row === null || ! $row->isUsable()) {
            return null;
        }

        $row->update(['used_at' => now()]);

        return $row;
    }
}
