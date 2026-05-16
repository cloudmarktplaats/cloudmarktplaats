<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Builds and parses EIP-4361 (Sign-In With Ethereum) messages.
 *
 * Spec: https://eips.ethereum.org/EIPS/eip-4361
 *
 * Domain + URI are taken from app config and bound to this instance at
 * construction time so the verify endpoint can validate them against the
 * incoming message. The chain id defaults to mainnet (1).
 */
class SiweMessageBuilder
{
    public function __construct(
        private string $domain,
        private string $uri,
        private int $chainId = 1,
    ) {}

    public function build(string $address, string $nonce, string $issuedAt): string
    {
        return implode("\n", [
            "{$this->domain} wants you to sign in with your Ethereum account:",
            $address,
            '',
            'Sign in to cloudmarktplaats.nl',
            '',
            "URI: {$this->uri}",
            'Version: 1',
            "Chain ID: {$this->chainId}",
            "Nonce: {$nonce}",
            "Issued At: {$issuedAt}",
        ]);
    }

    /**
     * @return array{address: string, uri: string, chain_id: int, nonce: string, issued_at: string}
     */
    public function parse(string $message): array
    {
        $lines = explode("\n", $message);

        return [
            'address' => trim($lines[1] ?? ''),
            'uri' => trim(str_replace('URI:', '', $lines[5] ?? '')),
            'chain_id' => (int) trim(str_replace('Chain ID:', '', $lines[7] ?? '')),
            'nonce' => trim(str_replace('Nonce:', '', $lines[8] ?? '')),
            'issued_at' => trim(str_replace('Issued At:', '', $lines[9] ?? '')),
        ];
    }
}
