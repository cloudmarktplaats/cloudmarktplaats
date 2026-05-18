<?php

use App\Http\Middleware\LegalAcceptance;
use App\Http\Middleware\RoleMiddleware;
use App\Jobs\IpStripperJob;
use Illuminate\Console\Scheduling\Schedule;
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

        // Production sits behind a Caddy reverse proxy on a separate host;
        // trust X-Forwarded-* so request scheme/host/IP reflect the public
        // edge (e.g. for https URL generation and rate-limit keys).
        $middleware->trustProxies(at: '*');

        // `role:admin,moderator` guards staff-only routes such as the
        // Filament admin panel.
        // `legal` re-prompts users to accept the latest ToS/privacy
        // when a new revision has been published since their last
        // acceptance — applied to legally-consequential routes (the
        // listing wizard, etc.). See {@see LegalAcceptance}.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'legal' => LegalAcceptance::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Hourly IP-retention sweep — see {@see IpStripperJob}.
        // 24h is the window we publish in the privacy statement; this
        // job is what makes that promise enforceable.
        $schedule->job(new IpStripperJob)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
