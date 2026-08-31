<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Livewire\Mail\Subscribe;
use App\Mail\OfferDigestMail;
use App\Models\Listing;
use App\Models\MailSubscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * De wekelijkse aanbodmail: wat er nieuw bij kwam in de categorieen die iemand
 * zelf aanvinkte.
 *
 * De regel die alles bepaalt: geen nieuws is geen mail. Is er in jouw
 * categorieen niets bij gekomen, dan krijg je niets en blijft de stempel staan,
 * zodat de week erop nog steeds klopt wat "nieuw" is. Een lijst die ook zwijgt
 * is het verschil tussen een nieuwsbrief en spam.
 *
 * Nieuw is nieuw sinds `offers_sent_at`, en voor wie nog nooit iets kreeg sinds
 * `created_at`: het moment van aanmelden. Wie zich vandaag aanmeldt heeft het
 * aanbod van vandaag net zelf zien staan; dat terugsturen zou een catalogus
 * zijn, geen nieuwsbericht, en meteen de langste mail die we ooit versturen.
 *
 * Draai eerst met --dry-run. Het commando toont dan per abonnee hoeveel
 * advertenties hij zou krijgen, zonder te versturen en zonder te stempelen.
 */
class SendOfferDigest extends Command
{
    protected $signature = 'mail:offers
                            {--dry-run : Laat zien wie er gemaild zou worden, verstuur niets}';

    protected $description = 'Mail nieuw aanbod in de gekozen categorieen, en alleen als er iets nieuws is';

    public function handle(): int
    {
        // Dezelfde noodrem als het aanmeldformulier: staat de vlag uit, dan
        // bestaat deze mail niet. Exitcode 0, want een uitgezette functie is
        // geen storing die de scheduler moet melden.
        if (! config('cloudmarktplaats.features.mail_list')) {
            $this->info('features.mail_list staat uit; er wordt niets verstuurd.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        // `published_at` is het ijkpunt van "nieuw", dus een gepubliceerde rij
        // zonder die datum valt buiten elke selectie hieronder. Dat is de
        // veilige kant (liever missen dan oude voorraad als nieuw versturen) en
        // via de state machine kan het niet ontstaan: `transition()` stempelt
        // `published_at` in dezelfde save. Gemeten op 31-08: productie 0 van 52,
        // de ontwikkeldatabase 3 van 12 uit juli. Handwerk in de database kan
        // zo'n rij dus wel maken, en dan hoort hij geteld te worden in plaats
        // van stil te verdwijnen; dit commando is de enige plek die er kijkt.
        $zonderDatum = Listing::query()->where('state', 'published')->whereNull('published_at')->count();
        if ($zonderDatum > 0) {
            $this->warn(sprintf(
                '%d gepubliceerde advertentie(s) zonder publicatiedatum; die vallen buiten deze mail.',
                $zonderDatum,
            ));
        }

        // `confirmed()` is het enige gezaghebbende filter op bevestiging: een
        // bevestigde rij kan tegelijk een levend `confirm_token` dragen als er
        // een wijziging geparkeerd staat. En `wants_offers` staat er los naast,
        // want wie zich voor het aanbod afmeldde houdt `confirmed_at` voor de
        // updates. Wel bevestigd en toch afgemeld is dus een echt geval.
        $subscriptions = MailSubscription::query()
            ->confirmed()
            ->where('wants_offers', true)
            ->orderBy('id')
            ->get();

        $rows = [];
        $mailed = 0;
        $advertenties = 0;
        foreach ($subscriptions as $sub) {
            $listings = $this->newListingsFor($sub);

            if ($listings->isEmpty()) {
                // Niets sturen én niet stempelen: anders schuift het ijkpunt op
                // en valt alles wat er deze week bij kwam volgende week buiten.
                continue;
            }

            // Adressen alleen bij een proefdraai. Een echte ronde draait in de
            // scheduler en die uitvoer landt in een logbestand: een lijst
            // e-mailadressen hoort daar niet in, want dat is een tweede kopie
            // van de tabel buiten elk bewaarbeleid om. Bij --dry-run kijkt er
            // een mens mee die juist wil zien wie er aan de beurt is.
            if ($dryRun) {
                $rows[] = [
                    $sub->email,
                    $sub->user_id === null ? 'geen account' : 'account',
                    $listings->count(),
                    'zou mailen',
                ];
            }

            $advertenties += $listings->count();

            if (! $dryRun) {
                Mail::to($sub->email)->queue(new OfferDigestMail($sub, $listings));
                // Query builder en niet Eloquent: een modelupdate zet ook
                // `updated_at`, en dat vak zegt hier iets anders (wanneer de
                // voorkeuren wijzigden). Zie RemindDraftListings voor de keer
                // dat dit onderscheid echt misging.
                DB::table('mail_subscriptions')->where('id', $sub->id)->update(['offers_sent_at' => now()]);
            }

            $mailed++;
        }

        if ($mailed === 0) {
            $this->info('Geen nieuw aanbod voor wie op de lijst staat. Er gaat niets uit.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line($dryRun ? '<comment>DRY RUN — er wordt niets verstuurd</comment>' : '<info>VERSTUREN</info>');
        $this->newLine();

        if ($dryRun) {
            $this->table(['e-mail', 'segment', 'advertenties', 'status'], $rows);
        }

        $this->info(sprintf(
            '%d van %d abonnees, %d advertenties. %s',
            $mailed,
            $subscriptions->count(),
            $advertenties,
            $dryRun ? 'Draai zonder --dry-run om te versturen.' : 'Verstuurd (via de queue).',
        ));

        return self::SUCCESS;
    }

    /**
     * Wat er sinds het ijkpunt bij kwam in de hoofdcategorieen van deze abonnee.
     *
     * De categorieboom is een ltree, dus `subltree(path,0,1)` snijdt het bovenste
     * label eraf: een advertentie in `networking.switches` telt mee voor wie
     * `networking` aanvinkte. De slugs gaan langs de vaste lijst van het
     * formulier, zodat een oude of verminkte waarde in de kolom hier geen
     * onbedoelde categorie opent.
     *
     * @return Collection<int, Listing>
     */
    private function newListingsFor(MailSubscription $sub): Collection
    {
        $slugs = array_values(array_intersect((array) $sub->categories, Subscribe::CATEGORIES));
        $sinds = $sub->offers_sent_at ?? $sub->created_at;

        if ($slugs === [] || $sinds === null) {
            return new Collection;
        }

        return Listing::query()
            ->where('state', 'published')
            ->where('published_at', '>', $sinds)
            ->whereHas('category', fn ($q) => $q->whereIn(DB::raw('subltree(path,0,1)::text'), $slugs))
            ->with('category')
            ->orderByDesc('published_at')
            ->get();
    }
}
