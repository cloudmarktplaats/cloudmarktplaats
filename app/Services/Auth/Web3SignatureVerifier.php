<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Verifies an EIP-191 personal_sign signature against a claimed Ethereum
 * address by recovering the public key over secp256k1 and re-deriving the
 * 20-byte address.
 *
 * Spec: https://eips.ethereum.org/EIPS/eip-191
 *
 * Hash: keccak256("\x19Ethereum Signed Message:\n" . len(msg) . msg)
 */
class Web3SignatureVerifier
{
    public function verify(string $expectedAddress, string $message, string $signature): bool
    {
        if (! str_starts_with($signature, '0x') || strlen($signature) !== 132) {
            return false;
        }

        $sigHex = substr($signature, 2);
        $r = substr($sigHex, 0, 64);
        $s = substr($sigHex, 64, 64);
        $v = (int) hexdec(substr($sigHex, 128, 2));
        if ($v >= 27) {
            $v -= 27;
        }

        try {
            $hash = Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message, 256);

            $ec = new EC('secp256k1');
            $key = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $v);
            $x = str_pad($key->getX()->toString(16), 64, '0', STR_PAD_LEFT);
            $y = str_pad($key->getY()->toString(16), 64, '0', STR_PAD_LEFT);
            $pubBin = hex2bin($x.$y);
            if ($pubBin === false) {
                return false;
            }
            $recovered = '0x'.substr(Keccak::hash($pubBin, 256), -40);
        } catch (\Throwable) {
            return false;
        }

        return strtolower($recovered) === strtolower($expectedAddress);
    }
}
