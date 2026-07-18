<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt bevestigde 2FA af voor staff op de Filament-adminpanel.
 *
 * Draait ná {@see RoleMiddleware} (`role:admin,moderator`)
 * in de panel-`authMiddleware`, dus de gebruiker is gegarandeerd ingelogd én
 * staff. Een staffer zonder `two_factor_confirmed_at` wordt naar de
 * 2FA-instelpagina gestuurd tot hij inschrijft. De instelpagina zelf is een
 * front-end-route buiten deze middleware, dus er is geen redirect-lus.
 */
class EnforceStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->two_factor_confirmed_at === null) {
            return redirect()->route('profile.security.2fa')->with(
                'status',
                __('Als moderator of admin is tweefactor-authenticatie verplicht. Stel het in om verder te gaan.'),
            );
        }

        return $next($request);
    }
}
