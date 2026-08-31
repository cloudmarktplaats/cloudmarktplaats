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
        //
        // Afmelden in 1 klik (RFC 8058) komt als POST binnen vanuit Gmail of
        // Yahoo zelf, buiten elke sessie om. Een CSRF-token bestaat daar niet,
        // dus de standaard is hier een gegarandeerde TokenMismatch. Het risico
        // dat de controle afdekt is hier bovendien nagenoeg leeg: het token in
        // de URL is het geheim, en het ergste wat een vervalst verzoek bereikt
        // is dat iemand géén reclame meer krijgt. Het herstelscherm biedt de
        // keuze terug aan.
        $middleware->validateCsrfTokens(except: [
            '/auth/web3/verify',
            'nieuwsbrief/afmelden/*',
        ]);

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

        // Dagelijkse duw richting blijven liggen concepten. Eén keer per
        // concept (zie `draft_reminded_at`), en pas na 48 uur. Om 10:00 omdat
        // een advertentie afmaken iets is dat je overdag even doet.
        $schedule->command('listings:remind-drafts')->dailyAt('10:00');

        // Dagelijkse integriteitscheck. Vroeg genoeg om er iets aan te doen
        // voordat er een dag overheen gaat. Zie IntegrityReport: dit rapport
        // telt ook wat er níet gebeurde, want daar zat de fotobug.
        $schedule->command('platform:daily-check')->dailyAt('07:30');

        // De wekelijkse aanbodmail. Zaterdagochtend, want dat is wanneer een
        // homelab-bouwer tijd heeft om ergens iets op te halen. Staat er niets
        // nieuws in iemands categorieen, dan verstuurt het commando niets: geen
        // nieuws is geen mail. De vlag `mail_list` is de noodrem.
        $schedule->command('mail:offers')->weeklyOn(6, '09:00');

        // Onbevestigde aanmeldingen zijn geen toestemming, dus die blijven niet
        // staan. Nachtelijk, want niemand hoeft dit te zien gebeuren, en ruim
        // voor de dagelijkse check van 07:30 zodat het getal in die mail de
        // stand na het opruimen is.
        $schedule->command('mail:purge-unconfirmed')->dailyAt('03:30');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
