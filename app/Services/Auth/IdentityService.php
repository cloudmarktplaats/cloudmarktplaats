<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserIdentity;

/**
 * Encapsulates the rules around linking and unlinking identities to a user.
 *
 * The marketplace allows multiple login methods per account (password,
 * OAuth GitHub/GitLab, SIWE) — but every account MUST retain at least one
 * working identity at all times. {@see canUnlink()} and {@see unlink()}
 * enforce that invariant so the security UI can't leave a user locked out.
 */
class IdentityService
{
    public function canUnlink(User $user, UserIdentity $identity): bool
    {
        return $user->identities()->where('id', '!=', $identity->id)->exists();
    }

    public function unlink(User $user, UserIdentity $identity): void
    {
        if (! $this->canUnlink($user, $identity)) {
            throw new LastIdentityException('Cannot remove last login method.');
        }
        $identity->delete();
    }
}
