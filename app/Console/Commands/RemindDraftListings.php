<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DraftReminderMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Herinnert verkopers aan een concept dat blijft liggen.
 *
 * Meting van 19-08: 16 concepten van 10 verkopers, tegenover 32 gepubliceerde
 * advertenties. Een derde van alles wat er ligt is halfaf, en niemand vertelt
 * die mensen dat. Vier van die concepten hebben al foto's — één klik van live.
 *
 * Eén mail per verkoper, één herinnering per concept (`draft_reminded_at`),
 * en pas na MIN_AGE_HOURS zodat wie er nú mee bezig is met rust gelaten wordt.
 *
 * Draai eerst met --dry-run: rommelconcepten ("test1", toetsenbordgeklets) zijn
 * niet betrouwbaar automatisch te herkennen, dus daar kijkt een mens naar. Voor
 * wie je wilt overslaan is er --exclude.
 */
class RemindDraftListings extends Command
{
    protected $signature = 'listings:remind-drafts
                            {--dry-run : Laat zien wie er gemaild zou worden, verstuur niets}
                            {--exclude=* : User-ids om over te slaan}
                            {--min-age=48 : Minimale leeftijd van het concept in uren}';

    protected $description = 'Mail verkopers één keer over hun blijven liggen concepten';

    public function handle(): int
    {
        $exclude = array_map('intval', (array) $this->option('exclude'));
        $minAge = max(1, (int) $this->option('min-age'));

        /** @var Collection<int, Listing> $stale */
        $stale = Listing::query()
            ->where('state', 'draft')
            ->whereNull('draft_reminded_at')
            // updated_at, niet created_at: de wizard bewaart bij elke stap, dus
            // dit is het moment waarop iemand er echt mee ophield.
            ->where('updated_at', '<=', now()->subHours($minAge))
            ->whereHas('user', fn ($q) => $q->where('is_banned', false)->whereNotNull('email'))
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('user_id', $exclude))
            ->with('user')
            ->orderBy('user_id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Geen concepten om aan te herinneren.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->newLine();

        $rows = [];
        $reminded = 0;
        foreach ($stale->groupBy('user_id') as $userId => $listings) {
            /** @var Listing $first */
            $first = $listings->first();
            $user = $first->user;

            if (! $user instanceof User || $user->email === null) {
                $rows[] = [$userId, '(geen eigenaar/e-mail)', $listings->count(), '', 'OVERGESLAGEN'];

                continue;
            }

            $rows[] = [
                $userId,
                $user->email,
                $listings->count(),
                $listings->pluck('title')->take(3)->implode(', '),
                $dryRun ? 'zou mailen' : 'gemaild',
            ];

            if (! $dryRun) {
                Mail::to($user->email)->send(new DraftReminderMail($user, $listings));
                // Pas markeren ná het versturen: valt de mail om, dan komt dit
                // concept morgen gewoon opnieuw langs.
                // Query builder, niet Eloquent: die zet ook `updated_at` en
                // dat maakt een blijven liggen concept kunstmatig "vers", zodat
                // het uit `concepten_zonder_foto` in de dagelijkse mail valt.
                // Zie NotifyPhotoBugDrafts voor het geval waarin dat echt misging.
                DB::table('listings')->whereIn('id', $listings->pluck('id'))->update(['draft_reminded_at' => now()]);
            }

            $reminded++;
        }

        $this->table(['user', 'e-mail', '#', 'concepten', 'status'], $rows);
        $this->info(sprintf(
            '%d concepten, %d ontvangers. %s',
            $stale->count(),
            $reminded,
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }
}
