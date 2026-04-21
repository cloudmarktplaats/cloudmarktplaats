<?php

namespace App\Services\Auth;

use Elliptic\EC;
use kornrunner\Keccak;

class Web3SignatureVerifier
{
    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    public function verify(string $message, string $signature, string $claimedAddress): bool
    {
        try {
            $recovered = $this->recover($message, $signature);
        } catch (\Throwable $e) {
            return false;
        }
        return strtolower($recovered) === strtolower($claimedAddress);
    }

    public function recover(string $message, string $signatureHex): string
    {
        $signatureHex = strtolower($signatureHex);
        if (str_starts_with($signatureHex, '0x')) {
            $signatureHex = substr($signatureHex, 2);
        }
        if (strlen($signatureHex) !== 130) {
            throw new \InvalidArgumentException('Signature must be 65 bytes (130 hex chars)');
        }

        $r = substr($signatureHex, 0, 64);
        $s = substr($signatureHex, 64, 64);
        $v = hexdec(substr($signatureHex, 128, 2));

        if ($v >= 27) {
            $v -= 27;
        }
        if ($v !== 0 && $v !== 1) {
            throw new \InvalidArgumentException('Invalid recovery param v');
        }

        $prefixed = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $digest = Keccak::hash($prefixed, 256);

        $pubKey = $this->ec->recoverPubKey($digest, ['r' => $r, 's' => $s], $v);
        $pubHex = $pubKey->encode('hex', false);

        $pubBytes = hex2bin(substr($pubHex, 2));
        $addrHash = Keccak::hash($pubBytes, 256);
        return '0x' . substr($addrHash, -40);
    }
}
