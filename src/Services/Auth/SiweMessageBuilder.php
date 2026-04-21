<?php

namespace App\Services\Auth;

class SiweMessageBuilder
{
    public function __construct(private string $expectedDomain)
    {
    }

    public function build(
        string $address,
        int $chainId,
        string $nonce,
        string $uri,
        string $statement,
        ?string $issuedAt = null
    ): string {
        $issuedAt ??= gmdate('Y-m-d\TH:i:s\Z');

        return sprintf(
            "%s wants you to sign in with your Ethereum account:\n%s\n\n%s\n\nURI: %s\nVersion: 1\nChain ID: %d\nNonce: %s\nIssued At: %s",
            $this->expectedDomain,
            $address,
            $statement,
            $uri,
            $chainId,
            $nonce,
            $issuedAt
        );
    }

    public function parse(string $message): array
    {
        $lines = preg_split('/\r\n|\n/', $message);
        if (count($lines) < 7) {
            throw new \InvalidArgumentException('SIWE message too short');
        }

        if (!preg_match('/^(\S+) wants you to sign in with your Ethereum account:$/', $lines[0], $m)) {
            throw new \InvalidArgumentException('Invalid SIWE preamble');
        }
        $domain = $m[1];

        if ($domain !== $this->expectedDomain) {
            throw new \InvalidArgumentException("Domain mismatch: expected {$this->expectedDomain}, got {$domain}");
        }

        $address = trim($lines[1]);
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            throw new \InvalidArgumentException('Invalid address');
        }

        $parsed = ['domain' => $domain, 'address' => $address];

        foreach ($lines as $line) {
            if (preg_match('/^Chain ID: (\d+)$/', $line, $m)) {
                $parsed['chain_id'] = (int) $m[1];
            } elseif (preg_match('/^Nonce: ([A-Za-z0-9]+)$/', $line, $m)) {
                $parsed['nonce'] = $m[1];
            } elseif (preg_match('/^Issued At: (\S+)$/', $line, $m)) {
                $parsed['issued_at'] = $m[1];
            } elseif (preg_match('/^URI: (\S+)$/', $line, $m)) {
                $parsed['uri'] = $m[1];
            }
        }

        foreach (['chain_id', 'nonce', 'issued_at'] as $required) {
            if (!isset($parsed[$required])) {
                throw new \InvalidArgumentException("Missing SIWE field: {$required}");
            }
        }

        $issuedTs = strtotime($parsed['issued_at']);
        if ($issuedTs === false) {
            throw new \InvalidArgumentException('Invalid Issued At timestamp');
        }
        $drift = abs(time() - $issuedTs);
        if ($drift > 600) {
            throw new \InvalidArgumentException('Issued At too far from server time (>10min drift)');
        }

        return $parsed;
    }
}
