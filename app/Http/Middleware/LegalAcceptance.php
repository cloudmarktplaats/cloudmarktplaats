<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LegalDocument;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-prompts authenticated users to accept the latest ToS / privacy
 * version whenever a new revision has been published since they last
 * agreed.
 *
 * Acceptance is a *per-version* record: when the admin publishes
 * `legal/tos@1.1.0`, every user who only ever accepted `1.0.0` becomes
 * "stale" and is funnelled through `/legal/accept` on their next
 * page-request that runs through this middleware.
 *
 * The `/legal/accept` route itself and `/logout` are exempt; everything
 * else aborts the request with a redirect so users can't sneak through
 * via deep-links. The middleware is opt-in (alias `legal`) — we apply
 * it to the wizard flow because creating a listing is the legally
 * consequential action; browse / login / passive reads do not require
 * fresh acceptance.
 */
class LegalAcceptance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        // The acceptance prompt page and the logout endpoint MUST stay
        // reachable; otherwise we lock the user into an infinite loop
        // (or worse, deny them a way to leave the session).
        if ($request->is('legal/accept') || $request->is('logout')) {
            return $next($request);
        }

        foreach (['tos', 'privacy'] as $type) {
            $current = LegalDocument::current($type, app()->getLocale());
            if ($current === null) {
                continue;
            }

            $accepted = $user->legalAcceptances()
                ->where('legal_document_id', $current->id)
                ->exists();

            if (! $accepted) {
                return new RedirectResponse('/legal/accept');
            }
        }

        return $next($request);
    }
}
