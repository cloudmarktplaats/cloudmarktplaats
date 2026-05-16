<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The SIWE verify endpoint is called from a wallet adapter (MetaMask /
        // WalletConnect) which doesn't carry a Laravel session token. Replay
        // protection is provided by the single-use nonce in `auth_nonces`.
        $middleware->validateCsrfTokens(except: ['/auth/web3/verify']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
