<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role guard for staff-only routes (Filament admin panel, moderation APIs).
 *
 * Usage:
 *   Route::middleware(['auth', 'role:admin,moderator'])->group(...)
 *
 * The middleware aborts with 403 if no authenticated user is present or the
 * user lacks one of the listed roles. Pair with `auth` so an anonymous user
 * is redirected to the login page first; we do not redirect here because
 * `role` semantically means "you are signed in but not allowed".
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasRole(...$roles)) {
            abort(403);
        }

        return $next($request);
    }
}
