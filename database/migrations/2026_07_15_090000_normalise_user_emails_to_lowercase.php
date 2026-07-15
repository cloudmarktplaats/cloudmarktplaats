<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lowercase existing emails so accounts created before User::email() existed
 * can log in and reset their password.
 *
 * Postgres compares case-sensitively: an account stored as
 * "B.vaneijk@outlook.com" never matched a login or reset for
 * "b.vaneijk@outlook.com". The lookup found no user, so no reset token was
 * created and no mail was sent, while the UI reported success — reported by a
 * real user on 2026-07-15 as "reset mails komen niet aan".
 *
 * Safety: `users_email_unique` is case-sensitive, so if two accounts differ
 * only in casing, lowercasing both would violate it. That cannot be resolved
 * automatically — merging accounts is a product decision, not a migration — so
 * we detect it and stop with a message naming the addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $collisions = DB::table('users')
            ->selectRaw('lower(email) AS normalised, count(*) AS n')
            ->groupByRaw('lower(email)')
            ->havingRaw('count(*) > 1')
            ->pluck('normalised')
            ->all();

        if ($collisions !== []) {
            throw new RuntimeException(
                'Cannot normalise emails: these addresses exist more than once ignoring case — '
                .implode(', ', $collisions)
                .'. Merge or remove the duplicates first; picking a winner is not a migration decision.'
            );
        }

        DB::table('users')
            ->whereRaw('email <> lower(email)')
            ->update(['email' => DB::raw('lower(email)')]);
    }

    public function down(): void
    {
        // Irreversible by nature: the original casing is not recorded anywhere,
        // and restoring it would re-break the accounts this migration repaired.
    }
};
