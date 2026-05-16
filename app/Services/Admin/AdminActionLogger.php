<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminAction;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit trail for moderator/admin actions.
 *
 * Every Filament action that mutates user-visible state (rejecting a
 * listing, banning a user, resolving a report, …) calls
 * {@see AdminActionLogger::log()} with a stable action label and the
 * relevant metadata. The resulting `admin_actions` row carries:
 *
 *   - the operator (`user_id`)
 *   - the action label (`action`)
 *   - the morph-mapped target (`target_type`, `target_id`)
 *   - arbitrary context (`meta` jsonb)
 *   - a non-reversible operator IP fingerprint (`ip_hash`)
 *
 * The IP is hashed with `app.key` as a per-deployment salt so log dumps
 * cannot be replayed against rainbow tables of public IPs, but identical
 * operators stay correlatable inside a single deployment for abuse review.
 *
 * The class is intentionally a thin static facade — Filament action
 * closures already operate inside a request context, so injecting a
 * service into every closure is more friction than it's worth.
 */
class AdminActionLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        string $action,
        string $targetType,
        int $targetId,
        array $meta = [],
    ): AdminAction {
        return AdminAction::query()->create([
            'user_id' => (int) Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta === [] ? null : $meta,
            'ip_hash' => self::ipHash(),
            'created_at' => now(),
        ]);
    }

    private static function ipHash(): string
    {
        $ip = request()->ip() ?? '0.0.0.0';

        return hash('sha256', $ip.config('app.key'));
    }
}
