<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ListingPhotoBugMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * One-off: mail the sellers whose listing got stuck as a draft because the
 * photo upload crashed on any phone-sized photo.
 *
 * A listing needs a photo to be published, so these people filled in every
 * field and then hit a wall they could not get past. This tells them it wasn't
 * their fault and points them back at the wizard.
 *
 * Targets drafts with zero photos. That is deliberately broad — someone who
 * simply hasn't finished yet also matches — so the mail is written to be
 * harmless in that case ("voeg je foto toe en zet 'm live" is true either way),
 * and --dry-run exists to eyeball the list before anything is sent.
 *
 * Delete this command once the backlog is cleared; it has no recurring purpose.
 */
class NotifyPhotoBugDrafts extends Command
{
    protected $signature = 'listings:notify-photo-bug
                            {--dry-run : Show who would be mailed, send nothing}
                            {--exclude=* : User ids to skip (e.g. your own test drafts)}';

    protected $description = 'Mail sellers whose draft got stuck because the photo upload crashed';

    public function handle(): int
    {
        $exclude = array_map('intval', (array) $this->option('exclude'));

        /** @var Collection<int, Listing> $stuck */
        $stuck = Listing::query()
            ->where('state', 'draft')
            ->whereDoesntHave('photos')
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('user_id', $exclude))
            ->with('user')
            ->orderBy('user_id')
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('Geen vastgelopen concepten gevonden.');

            return self::SUCCESS;
        }

        // One mail per seller, not per listing.
        $perUser = $stuck->groupBy('user_id');
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->newLine();

        $rows = [];
        $sendable = 0;
        foreach ($perUser as $userId => $listings) {
            /** @var Listing $first */
            $first = $listings->first();
            $user = $first->user;

            $titles = $listings->pluck('title')->implode(', ');

            if (! $user instanceof User || $user->email === null) {
                $rows[] = [$userId, '(geen eigenaar/e-mail)', $listings->count(), $titles, 'OVERGESLAGEN'];

                continue;
            }

            $rows[] = [$userId, $user->email, $listings->count(), $titles, $dryRun ? 'zou mailen' : 'gemaild'];
            $sendable++;

            if (! $dryRun) {
                Mail::to($user->email)->send(new ListingPhotoBugMail($user, $listings));
            }
        }

        $this->table(['user', 'e-mail', '#', 'advertenties', 'status'], $rows);
        $this->info(sprintf(
            '%d concepten, %d ontvangers. %s',
            $stuck->count(),
            $sendable,
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }
}
