<?php

declare(strict_types=1);

use Elliptic\EC;
use kornrunner\Keccak;

if (! function_exists('signMessage')) {
    /**
     * Sign an arbitrary message using EIP-191 personal_sign over secp256k1.
     *
     * Test-only helper. Loaded via tests/Pest.php so it is available across
     * the SIWE unit + feature suites without polluting application code.
     *
     * Returns a 0x-prefixed 65-byte hex signature (r || s || v).
     */
    function signMessage(string $message, string $privKey): string
    {
        $hash = Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message, 256);
        $ec = new EC('secp256k1');
        $sig = $ec->keyFromPrivate(ltrim($privKey, '0x'))->sign($hash, ['canonical' => true]);

        $r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = str_pad(dechex($sig->recoveryParam + 27), 2, '0', STR_PAD_LEFT);

        return '0x'.$r.$s.$v;
    }
}
