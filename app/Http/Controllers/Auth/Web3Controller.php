<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Auth\SiweMessageBuilder;
use App\Services\Auth\Web3NonceGenerator;
use App\Services\Auth\Web3SignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Backend for the Sign-In With Ethereum (EIP-4361) flow.
 *
 * GET  /auth/web3/nonce — issues a fresh nonce + EIP-4361 message
 * POST /auth/web3/verify — validates the signature, logs in known wallets,
 *                         or returns an onboarding flag for first-time users.
 *
 * Both endpoints are JSON-only and called by a browser-side wallet
 * adapter (MetaMask / WalletConnect). The verify endpoint is CSRF-exempt
 * because it's POSTed without a Laravel session token by the wallet JS.
 */
class Web3Controller extends Controller
{
    public function __construct(
        private Web3NonceGenerator $nonces,
        private Web3SignatureVerifier $verifier,
        private SiweMessageBuilder $builder,
    ) {}

    public function nonce(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
        ]);

        $key = 'siwe:nonce:'.($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $address = $validated['address'];
        $nonce = $this->nonces->issue($address);
        $message = $this->builder->build($address, $nonce->nonce, now()->toIso8601String());

        return response()->json([
            'nonce' => $nonce->nonce,
            'message' => $message,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'signature' => ['required', 'regex:/^0x[a-fA-F0-9]{130}$/'],
            'message' => ['required', 'string'],
        ]);

        $key = 'siwe:verify:'.($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $address = $validated['address'];
        $message = $validated['message'];
        $signature = $validated['signature'];

        $parsed = $this->builder->parse($message);
        $consumed = $this->nonces->consume($parsed['nonce'], $address);
        if ($consumed === null) {
            throw ValidationException::withMessages(['nonce' => 'Nonce ongeldig of verlopen.']);
        }

        if (! $this->verifier->verify($address, $message, $signature)) {
            throw ValidationException::withMessages(['signature' => 'Handtekening klopt niet.']);
        }

        $lowerAddress = strtolower($address);

        // 1. Existing SIWE identity → log in returning user.
        $identity = UserIdentity::where('provider', 'siwe')
            ->where('provider_uid', $lowerAddress)
            ->first();
        if ($identity !== null) {
            $identityUser = $identity->user;
            if ($identityUser instanceof User) {
                $identity->update(['last_used_at' => now()]);

                // Gate on 2FA before completing login.
                if ($identityUser->two_factor_confirmed_at !== null) {
                    $request->session()->put('pending_2fa_user_id', $identityUser->id);

                    return response()->json(['ok' => true, 'redirect' => '/2fa/challenge']);
                }

                auth()->login($identityUser);
                $this->postLogin($request, $identityUser);
            }

            return response()->json(['ok' => true, 'redirect' => '/']);
        }

        // 2. No silent merge — a wallet address can't claim an existing
        //    user. Front-end routes to onboarding to collect username +
        //    legal acceptance and create both the user and the identity.
        return response()->json([
            'ok' => true,
            'onboarding_required' => true,
            'address' => $lowerAddress,
        ]);
    }

    private function postLogin(Request $request, User $user): void
    {
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }
}
