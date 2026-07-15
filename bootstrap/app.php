<?php

use App\Http\Middleware\LegalAcceptance;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Jobs\IpStripperJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    // Laravel enables event auto-discovery by default, which scans
    // app/Listeners for handle() methods type-hinted to an event and
    // registers them *in addition* to any manual Event::listen() call.
    // This app has no EventServiceProvider and registers every listener
    // explicitly in AppServiceProvider::boot() — with discovery left on,
    // every such listener silently fired twice per event (masked so far
    // only by listeners' own idempotency checks, e.g.
    // AwardInviteKarmaOnFirstListing). Disable it so one registration
    // means one execution.
    ->withEvents(discover: false)
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

        // Set the app locale (nl default, en optional) from the session on
        // every web request — driven by the language switcher.
        $middleware->web(append: [SetLocale::class]);

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

        // Weekly truncate of the nginx access log. Not a retention measure —
        // that log holds no IP (see docker/nginx/default.conf) — purely so it
        // doesn't grow unbounded. Sunday 04:00, when nobody is reading reports.
        $schedule->command('traffic:truncate-log')->weeklyOn(0, '04:00');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
