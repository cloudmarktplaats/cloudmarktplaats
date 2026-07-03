<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * User management is admin-only. Without this policy, Filament left the
 * UserResource edit/create/delete abilities ungated — a moderator (who
 * can access the panel) could reach /admin/users/{id}/edit by direct URL
 * and change another user's role, is_banned, etc. The resource's own
 * canViewAny() already restricts the list to admins; this closes the
 * remaining abilities. Moderators keep their moderation surface
 * (homelab posts, reports, listing approval) — none of which touch this
 * resource.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }
}
