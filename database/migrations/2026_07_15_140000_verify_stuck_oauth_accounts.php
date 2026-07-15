<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verifies OAuth accounts that were never verifiable through no fault of theirs.
 *
 * OAuthController passed 'email_verified_at' => now() to User::create(), but
 * that column is absent from User::$fillable, so mass assignment dropped it
 * silently. And unlike the password flow, OAuth fires no Registered event, so
 * no verification mail went out either. The result: accounts that could not
 * place a listing or send an invite (both routes require `verified`), with no
 * way to fix it themselves. On 2026-07-15 that was 14 of 17 GitHub accounts.
 *
 * Safe to verify these: the provider only hands back a *primary, verified*
 * address (Socialite's GithubProvider requests the user:email scope and reads
 * /user/emails), so the address was already proven to belong to them — we just
 * failed to record it.
 *
 * Scoped tightly:
 *  - only accounts holding an oauth_* identity,
 *  - only where the address is real (never the uid@provider.local placeholder:
 *    mail there goes nowhere, so "verified" would be a lie),
 *  - only where it is still null (never re-stamp an existing timestamp).
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('deleted_at')
            ->whereNotLike('email', '%.local')
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('user_identities')
                ->whereColumn('user_identities.user_id', 'users.id')
                ->where('user_identities.provider', 'like', 'oauth_%'))
            ->update(['email_verified_at' => now()]);

        // A migration is the wrong place to be silent about touching accounts.
        echo "  Verified {$affected} OAuth account(s) that could never verify themselves.\n";
    }

    public function down(): void
    {
        // Irreversible on purpose: we cannot tell which of these were null
        // because of the bug and which for another reason, and re-breaking
        // working accounts is worse than leaving them working.
    }
};
