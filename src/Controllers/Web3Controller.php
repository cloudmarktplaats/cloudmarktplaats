<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Models\AuthNonce;
use App\Models\User;
use App\Models\WalletAddress;
use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3NonceGenerator;
use App\Services\Auth\Web3SignatureVerifier;

class Web3Controller extends BaseController
{
    private Web3NonceGenerator $nonces;
    private SiweMessageBuilder $builder;
    private Web3SignatureVerifier $verifier;
    private WalletAddress $wallets;
    private User $userModel;
    private RateLimiter $rate;

    public function __construct()
    {
        parent::__construct();
        $this->nonces = new Web3NonceGenerator(new AuthNonce());
        $this->verifier = new Web3SignatureVerifier();
        $this->wallets = new WalletAddress();
        $this->userModel = new User();
        $this->rate = new RateLimiter();

        $domain = parse_url((string) Config::get('APP_URL', 'http://localhost:8000'), PHP_URL_HOST) ?: 'localhost';
        $this->builder = new SiweMessageBuilder($domain);
    }

    public function nonce(): void
    {
        header('Content-Type: application/json');

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$this->rate->attempt('web3_nonce:' . $clientIp, 10, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $address = strtolower((string) ($payload['address'] ?? ''));
        $chainId = (int) ($payload['chain_id'] ?? 0);

        if (!preg_match('/^0x[a-f0-9]{40}$/', $address) || $chainId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid address or chain_id']);
            return;
        }

        $nonce = $this->nonces->issue($address);
        $message = $this->builder->build(
            $address,
            $chainId,
            $nonce,
            (string) Config::get('APP_URL', 'http://localhost:8000'),
            'Log in bij Cloudmarkplaats'
        );

        echo json_encode(['nonce' => $nonce, 'message' => $message]);
    }

    public function verify(): void
    {
        header('Content-Type: application/json');

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$this->rate->attempt('web3_verify:' . $clientIp, 5, 60)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $message = (string) ($payload['message'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');

        try {
            $parsed = $this->builder->parse($message);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid SIWE message: ' . $e->getMessage()]);
            return;
        }

        $address = strtolower($parsed['address']);

        if (!$this->verifier->verify($message, $signature, $address)) {
            http_response_code(401);
            echo json_encode(['error' => 'Signature verification failed']);
            return;
        }

        if (!$this->nonces->verifyAndConsume($parsed['nonce'], $address)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired nonce']);
            return;
        }

        $userId = $this->linkOrCreate($address, $parsed['chain_id']);
        $user = $this->userModel->findById($userId);

        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('role', $user['role'] ?? 'user');
        session_regenerate_id(true);
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        echo json_encode(['ok' => true, 'redirect' => '/dashboard']);
    }

    public function linkOrCreate(string $address, int $chainId): int
    {
        $address = strtolower($address);
        $existing = $this->wallets->findByAddress($address);
        if ($existing !== false) {
            return (int) $existing['user_id'];
        }

        $username = $this->deriveWalletUsername($address);
        $userId = Database::getInstance()->insert('users', [
            'username' => $username,
            'email' => null,
            'password' => null,
            'role' => 'user',
        ]);
        $this->wallets->link($userId, $address, $chainId);
        return $userId;
    }

    public function deriveWalletUsername(string $address): string
    {
        $base = 'wallet_' . substr(strtolower($address), 2, 8);
        $candidate = $base;
        $suffix = 2;
        while ($this->userModel->existsWithUsername($candidate)) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                $candidate = $base . '_' . bin2hex(random_bytes(3));
                break;
            }
        }
        return $candidate;
    }
}
