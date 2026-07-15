<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\WaitlistInviteMail;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Invites people off the waitlist: one code each, mailed, marked as invited.
 *
 * The founding cohort filled at 100 on 2026-07-15 and registration closed, so
 * new arrivals landed on a waitlist — 15 within five hours. An invite now opens
 * that door (Register::submit), but nothing existed to hand one out: the admin
 * panel only has a manual "invited" toggle, so this was 15x generate-a-code,
 * copy it, mail it, tick the box.
 *
 * Codes are created directly rather than through InviteService::generate(),
 * which spends an invite credit. Credits are a gamification budget for members
 * inviting friends; clearing your own waitlist is an operator action and should
 * not be rationed by it (the admin had 9 credits and 15 people waiting). The
 * code still records who invited them, so karma and the invite graph stay
 * intact.
 *
 * Deliberately no --limit: the whole point is to clear the queue. Run it again
 * later and only new entries get mailed — `invited` is the ledger.
 */
class InviteWaitlist extends Command
{
    protected $signature = 'waitlist:invite
                            {--dry-run : Laat zien wie een uitnodiging krijgt, verstuur niets}
                            {--inviter= : E-mail van het account dat uitnodigt (default: eerste admin)}';

    protected $description = 'Nodigt iedereen op de wachtlijst uit met een werkende invite-link';

    public function handle(): int
    {
        $inviter = $this->resolveInviter();
        if ($inviter === null) {
            return self::FAILURE;
        }

        $pending = WaitlistEntry::query()
            ->where('invited', false)
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Niemand op de wachtlijst die nog een uitnodiging moet krijgen.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->line("Uitnodiger: {$inviter->email}");
        $this->newLine();

        $rows = [];
        foreach ($pending as $entry) {
            if ($dryRun) {
                $rows[] = [$entry->id, $entry->email, $entry->created_at?->format('d-m H:i'), 'zou mailen'];

                continue;
            }

            // Code + "invited" flag in one transaction: a code handed out but
            // not recorded means we mail this person again on the next run.
            $code = DB::transaction(function () use ($inviter, $entry): InviteCode {
                $code = InviteCode::query()->create(['inviter_user_id' => $inviter->id]);
                $entry->forceFill(['invited' => true])->save();

                return $code;
            });

            Mail::to($entry->email)->send(new WaitlistInviteMail($code));
            $rows[] = [$entry->id, $entry->email, $entry->created_at?->format('d-m H:i'), $code->code];
        }

        $this->table(['#', 'e-mail', 'op de lijst sinds', $dryRun ? 'status' : 'code'], $rows);
        $this->info(sprintf(
            '%d wachtenden. %s',
            $pending->count(),
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }

    private function resolveInviter(): ?User
    {
        $email = $this->option('inviter');

        $inviter = is_string($email) && $email !== ''
            ? User::query()->where('email', $email)->first()
            : User::query()->where('role', 'admin')->orderBy('id')->first();

        if (! $inviter instanceof User) {
            $this->error('Geen uitnodiger gevonden — geef --inviter=<e-mail> op.');

            return null;
        }

        // Mirrors InviteService::generate()'s guards: a banned or unverified
        // account should not be handing out invitations, however we create them.
        if ($inviter->is_banned) {
            $this->error("{$inviter->email} is geblokkeerd en kan niet uitnodigen.");

            return null;
        }
        if ($inviter->email_verified_at === null) {
            $this->error("{$inviter->email} heeft een niet-geverifieerd e-mailadres.");

            return null;
        }

        return $inviter;
    }
}
