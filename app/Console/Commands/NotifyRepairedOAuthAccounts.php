<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\OAuthAccountRepairedMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * One-off: tell the people whose OAuth account we broke that it works now.
 *
 * OAuthController passed `email_verified_at` to User::create() where that
 * column is not fillable, so it was dropped silently and every GitHub signup
 * stayed unverified — and that flow fires no Registered event, so no
 * verification mail went out either. Listing and inviting both require
 * `verified`, so these accounts could do nothing, for up to six days, with no
 * way out. Migration 2026_07_15_140000 repaired them; they have no idea.
 *
 * Selection: an oauth_* identity whose verification landed well after signup.
 * A healthy OAuth signup is verified within the same request (created_at ==
 * email_verified_at), so an hour's gap only exists for accounts the migration
 * touched. Deliberately not a hardcoded id list — that would rot the moment
 * anyone re-runs this against a restored database.
 *
 * Delete this command once sent; it has no recurring purpose.
 */
class NotifyRepairedOAuthAccounts extends Command
{
    protected $signature = 'oauth:notify-repaired {--dry-run : Laat zien wie een bericht krijgt, verstuur niets}';

    protected $description = 'Mailt de GitHub-gebruikers wier account door de verificatie-bug vastzat';

    public function handle(): int
    {
        $repaired = User::query()
            ->whereNotNull('email_verified_at')
            ->where('is_banned', false)
            ->whereRaw("email_verified_at > created_at + interval '1 hour'")
            ->whereNotLike('email', '%.local')
            ->whereHas('identities', fn (Builder $q) => $q->where('provider', 'like', 'oauth_%'))
            ->orderBy('id')
            ->get();

        if ($repaired->isEmpty()) {
            $this->info('Geen herstelde OAuth-accounts gevonden.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->newLine();

        $rows = [];
        foreach ($repaired as $user) {
            $stuckDays = $user->created_at?->diffInDays($user->email_verified_at) ?? 0;
            $rows[] = [
                $user->id,
                $user->email,
                $user->created_at?->format('d-m H:i'),
                $stuckDays.' dag(en)',
                $dryRun ? 'zou mailen' : 'gemaild',
            ];

            if (! $dryRun) {
                Mail::to($user->email)->send(new OAuthAccountRepairedMail($user));
            }
        }

        $this->table(['#', 'e-mail', 'aangemeld', 'vastgezeten', 'status'], $rows);
        $this->info(sprintf(
            '%d herstelde accounts. %s',
            $repaired->count(),
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }
}
