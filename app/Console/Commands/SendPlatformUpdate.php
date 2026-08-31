<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PlatformUpdateMail;
use App\Models\MailSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * De nieuwsbrief: wat er op het platform veranderde, geschreven door een mens.
 *
 * De rem staat hier in code en niet in een voornemen: ging de vorige editie
 * minder dan 30 dagen geleden uit, dan weigert dit commando. Dit platform
 * verkoopt dat elke belofte in de code te controleren is, dus "ik ga je niet
 * volspammen" hoort een regel te zijn die je kunt lezen, niet een zin in een
 * gesprek. Naast het vinkje op het aanmeldformulier staat hooguit 1 mail per
 * maand; dit is het bestand dat die zin waarmaakt.
 *
 * Niet gepland. Er is geen ritme dat een nieuwsbrief kan schrijven, dus dit
 * commando draait met de hand, altijd eerst met --dry-run. Een geplande versie
 * zou 12 keer per jaar een leeg bestand willen versturen en dan zou de rem het
 * enige zijn dat er nog tussen zit; dat is precies de verkeerde kant om op te
 * leunen.
 */
class SendPlatformUpdate extends Command
{
    protected $signature = 'mail:update
                            {bestand : Markdownbestand met de tekst, op de local-disk}
                            {--dry-run : Toon wie de mail zou krijgen, verstuur niets}
                            {--force : Negeer de 30-dagenrem, alleen voor een correctie}';

    protected $description = 'Mail de nieuwsbrief, en hooguit 1 keer per 30 dagen';

    /** De belofte naast het vinkje: hooguit 1 mail per maand. */
    private const REM_IN_DAGEN = 30;

    public function handle(): int
    {
        // Dezelfde noodrem als het aanmeldformulier. Anders dan bij de geplande
        // aanbodmail is dit exitcode 1: je typte dit commando zelf en er ging
        // niets uit, dus je opdracht is mislukt en niet "netjes overgeslagen".
        if (! config('cloudmarktplaats.features.mail_list')) {
            $this->error('features.mail_list staat uit; er wordt niets verstuurd.');

            return self::FAILURE;
        }

        $bestand = (string) $this->argument('bestand');
        if (! Storage::disk('local')->exists($bestand)) {
            $this->error(sprintf('Bestand niet gevonden op de local-disk: %s', $bestand));

            return self::FAILURE;
        }

        $tekst = trim((string) Storage::disk('local')->get($bestand));
        if ($tekst === '') {
            $this->error(sprintf('%s is leeg; een nieuwsbrief zonder tekst gaat niet uit.', $bestand));

            return self::FAILURE;
        }

        if (! $this->remIsOpen()) {
            return self::FAILURE;
        }

        // `confirmed()` is het enige gezaghebbende filter op bevestiging: een
        // bevestigde rij kan tegelijk een levend `confirm_token` dragen als er
        // een wijziging geparkeerd staat. `wants_updates` staat daar los naast
        // en zegt of iemand juist deze mail wil.
        $subscriptions = MailSubscription::query()
            ->confirmed()
            ->where('wants_updates', true)
            ->orderBy('id')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('Niemand staat op de lijst voor updates. Er gaat niets uit.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->newLine();

        if ($dryRun) {
            // Adressen alleen bij een proefdraai. Een echte ronde kan in een
            // logbestand landen en een lijst e-mailadressen hoort daar niet in:
            // dat is een tweede kopie van de tabel buiten elk bewaarbeleid om.
            $this->table(
                ['e-mail', 'segment'],
                $subscriptions->map(fn (MailSubscription $sub) => [
                    $sub->email,
                    $sub->user_id === null ? 'geen account' : 'account',
                ])->all(),
            );
            $this->line(collect(explode("\n", $tekst))->take(5)->implode("\n"));
            $this->newLine();
        }

        if (! $dryRun) {
            foreach ($subscriptions as $sub) {
                Mail::to($sub->email)->queue(new PlatformUpdateMail($sub, $tekst));

                // Query builder en niet Eloquent: een modelupdate zet ook
                // `updated_at`, en dat vak zegt hier iets anders (wanneer de
                // voorkeuren wijzigden). Zie SendOfferDigest, en
                // RemindDraftListings voor de keer dat dit onderscheid misging.
                //
                // Stempelen gebeurt zodra de mail de queue in gaat, precies als
                // bij de aanbodmail. Loopt de aflevering daarna alsnog stuk, dan
                // blijft de stempel staan: dit commando weet niets van de
                // afloop, en de herstelweg is een ronde met --force.
                DB::table('mail_subscriptions')->where('id', $sub->id)->update(['updates_sent_at' => now()]);
            }
        }

        $this->info(sprintf(
            '%d %s. %s',
            $subscriptions->count(),
            $subscriptions->count() === 1 ? 'ontvanger' : 'ontvangers',
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }

    /**
     * De rem. Hij kijkt naar `max(updates_sent_at)` over alle rijen, ook die van
     * wie zich daarna afmeldde: die stempel zegt wanneer de vorige editie
     * uitging, en dat feit verandert niet doordat iemand weg is.
     *
     * De rem geldt dus per editie en niet per ontvanger. Wie zich vorige week
     * aanmeldde krijgt de mail van vorige week niet alsnog, maar wacht op de
     * volgende. Anders zou 1 verse abonnee de deur openen en zou de grens geen
     * grens meer zijn maar een gemiddelde.
     */
    private function remIsOpen(): bool
    {
        $laatste = MailSubscription::query()->max('updates_sent_at');
        $laatste = $laatste === null ? null : Carbon::parse((string) $laatste);

        if ($laatste === null || $laatste->lte(now()->subDays(self::REM_IN_DAGEN))) {
            return true;
        }

        // --force is er voor het geval er echt iets misgaat en er een correctie
        // uit moet: een verkeerde link, een fout bedrag, een mail die de helft
        // van de lijst niet haalde. Nooit voor een gewone nieuwsbrief, want dan
        // is de rem een decoratie en de belofte een leugen.
        if ($this->option('force')) {
            $recent = MailSubscription::query()
                ->where('updates_sent_at', '>', now()->subDays(self::REM_IN_DAGEN))
                ->count();
            $this->warn(sprintf(
                'De rem is genegeerd met --force. %d abonnee(s) kregen in de afgelopen 30 dagen al een update; die krijgen deze mail er bovenop.',
                $recent,
            ));

            return true;
        }

        $teGaan = (int) ceil((float) now()->diffInDays($laatste->copy()->addDays(self::REM_IN_DAGEN), false));
        $this->error(sprintf(
            'De vorige nieuwsbrief ging %s uit. Nog %d dagen te gaan; hooguit 1 mail per 30 dagen.',
            $laatste->format('d-m-Y'),
            max($teGaan, 0),
        ));

        return false;
    }
}
