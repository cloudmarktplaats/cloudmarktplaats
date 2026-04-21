<?php
// Generates a fixture: signed SIWE message + expected signer address.
// Uses a deterministic test private key — NEVER use in production.
// Run: php tests/fixtures/siwe/generate.php > tests/fixtures/siwe/valid-mainnet.json

require __DIR__ . '/../../../vendor/autoload.php';

use Elliptic\EC;
use kornrunner\Keccak;

$privkey = '4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318';

$ec = new EC('secp256k1');
$key = $ec->keyFromPrivate($privkey);
$pubHex = $key->getPublic(false, 'hex');       // uncompressed, 04 + X + Y
$pubBytes = hex2bin(substr($pubHex, 2));       // strip leading 04 byte
$hash = Keccak::hash($pubBytes, 256);
$address = '0x' . substr($hash, -40);

$message = "cloudmarkplaats.test wants you to sign in with your Ethereum account:\n{$address}\n\nLog in bij Cloudmarkplaats\n\nURI: https://cloudmarkplaats.test\nVersion: 1\nChain ID: 1\nNonce: FIXTUREnonceFIXTUREnonceFIXTUREnn\nIssued At: " . gmdate('Y-m-d\TH:i:s\Z');

$prefixed = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
$digest = Keccak::hash($prefixed, 256);

$sig = $key->sign($digest, ['canonical' => true]);
$r = str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT);
$s = str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT);
$v = dechex(27 + $sig->recoveryParam);
$signature = '0x' . $r . $s . $v;

echo json_encode([
    'message' => $message,
    'signature' => $signature,
    'expected_address' => $address,
], JSON_PRETTY_PRINT) . "\n";
