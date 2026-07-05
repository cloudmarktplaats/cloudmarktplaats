<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale per request from the session (chosen via the
 * language switcher). Dutch is the source language, so it is the default
 * and the fallback: an untranslated string simply renders its Dutch key.
 */
class SetLocale
{
    /** @var list<string> */
    public const SUPPORTED = ['nl', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', 'nl');
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'nl';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
