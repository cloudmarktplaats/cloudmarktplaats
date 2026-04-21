<?php

namespace App\Services\Auth;

use App\Models\AuthNonce;

class Web3NonceGenerator
{
    private const TTL_SECONDS = 300;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct(private AuthNonce $nonces)
    {
    }

    public function issue(string $address): string
    {
        $address = strtolower($address);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $nonce = $this->randomString(32);
            try {
                $this->nonces->create($nonce, $address, self::TTL_SECONDS);
                return $nonce;
            } catch (\PDOException $e) {
                // unique collision — retry
            }
        }
        throw new \RuntimeException('Failed to issue unique nonce after 5 attempts');
    }

    public function verifyAndConsume(string $nonce, string $address): bool
    {
        $address = strtolower($address);
        $row = $this->nonces->findValid($nonce, $address);
        if ($row === false) {
            return false;
        }
        return $this->nonces->consume($nonce);
    }

    private function randomString(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }
}
