<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\MailSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Wat er in 24 uur gebeurde, en waar het stil bleef.
 *
 * Gebruikers melden geen kapotte site — die verdwijnen. De foto-upload lag zes
 * dagen plat en werd pas gevonden doordat iemand toevallig de log las; de
 * verkoper die er zes keer op stukliep zei niets en kwam niet terug.
 *
 * Daarom telt dit rapport niet alleen fouten maar ook **afwezigheid**: nul
 * foto's in een week is hier een alarm, geen rustige week.
 */
class IntegrityReport
{
    /** Hoeveel bytes van de staart van de log we lezen. */
    private const LOG_TAIL_BYTES = 400_000;

    public function __construct(
        private SecurityAdvisories $advisories,
        private UnansweredIssues $issues,
    ) {}

    /** @return array{cijfers: array<string, int>, fouten: list<array{aantal: int, regel: string}>, signalen: list<string>} */
    public function build(Carbon $now): array
    {
        $since = $now->copy()->subDay();
        $silenceDays = (int) config('cloudmarktplaats.ops.silence_days', 7);
        $quiet = $now->copy()->subDays($silenceDays);

        $cijfers = [
            'nieuwe_leden' => User::query()->where('created_at', '>=', $since)->count(),
            'gepubliceerd' => Listing::query()->where('published_at', '>=', $since)->count(),
            'fotos' => ListingPhoto::query()->where('created_at', '>=', $since)->count(),
            'contactverzoeken' => DB::table('contact_relay_logs')->where('created_at', '>=', $since)->count(),
            // Tellen op `completed_at`, niet op `updated_at`: dat laatste
            // beweegt ook als er iets anders aan de rij verandert.
            'deals_bevestigd' => Transaction::query()->where('status', 'completed')->where('completed_at', '>=', $since)->count(),
            'verkopen_gemeld' => Transaction::query()->where('created_at', '>=', $since)->count(),
            'mislukte_jobs' => DB::table('failed_jobs')->count(),
            // Sinds moderatie vóóraf eraf ging (22-08) is een melding van een
            // gebruiker het enige wat ons nog waarschuwt over wat er in het
            // aanbod staat. Die meldingen zaten alleen in het Filament-paneel,
            // waar je voor moet inloggen. Hier telt de openstaande voorraad,
            // niet de aanwas van een dag: een melding die drie dagen blijft
            // liggen is het probleem, niet de melding zelf.
            'meldingen_open' => DB::table('reports')->where('status', 'open')->count(),
            'concepten_zonder_foto' => $this->stuckDrafts($since)->count(),
            // Een getal, geen signaal. De lijst groeit langzaam en de voorraad
            // daalt zelden, dus alarmeren zou hier hetzelfde worden als bij
            // `concepten_zonder_foto`: elke ochtend dezelfde zin over dezelfde
            // rijen, waarna een echte verandering in de ruis verdwijnt.
            'nieuwsbrief_abonnees' => MailSubscription::query()->confirmed()->count(),
            // Afmeldingen als aanwas over een week, niet als totaal: een totaal
            // loopt alleen op en zegt over vandaag niets meer. Een week is
            // precies één verzendcyclus (`mail:offers` draait wekelijks), dus
            // de reactie op een editie past er nog helemaal in. Ook dit is een
            // getal zonder drempel: bij deze lijstgrootte is elk percentage
            // ruis, en een drempel die je nu verzint klopt straks niet meer.
            'afmeldingen_afgelopen_week' => MailSubscription::query()
                ->where('unsubscribed_at', '>=', $now->copy()->subWeek())
                ->count(),
        ];

        $fouten = $this->errorsSince($since);

        $signalen = [];
        if ($fouten !== []) {
            $signalen[] = sprintf('%d foutregel(s) in de log, zie hieronder.', array_sum(array_column($fouten, 'aantal')));
        }
        if ($cijfers['mislukte_jobs'] > 0) {
            $signalen[] = sprintf('%d mislukte job(s) in de wachtrij.', $cijfers['mislukte_jobs']);
        }

        // Vanaf de eerste melding, niet vanaf een drempel: er wordt hier zo
        // weinig gemeld dat elke melding er een is om vandaag naar te kijken.
        if ($cijfers['meldingen_open'] > 0) {
            $signalen[] = sprintf(
                '%d openstaande melding(en) over advertenties of homelab-posts — sinds moderatie vooraf eraf is, is dit het vangnet.',
                $cijfers['meldingen_open'],
            );
        }

        // De stiltes. Dit is het deel dat de fotobug had gevangen.
        if (! ListingPhoto::query()->where('created_at', '>=', $quiet)->exists()) {
            $signalen[] = sprintf('Geen enkele foto geüpload in %d dagen — controleer of uploaden nog werkt.', $silenceDays);
        }
        if (! Listing::query()->where('published_at', '>=', $quiet)->exists()) {
            $signalen[] = sprintf('Geen enkele advertentie gepubliceerd in %d dagen.', $silenceDays);
        }
        // Alarmeren op het totaal zou hier elke ochtend afgaan: die voorraad
        // daalt alleen als de verkoper zelf terugkomt, en een deel komt nooit
        // af. Dan verdwijnt een nieuw geval in de ruis — "10 concept(en)"
        // wordt "11" en dat leest als dezelfde ochtend als gisteren. Het
        // signaal telt daarom alleen wat er nog te dóen is: concepten waarvan
        // de eigenaar nog van niemand iets gehoord heeft. Het getal hierboven
        // blijft het volledige totaal, dus de voorraad blijft zichtbaar.
        $onaangeraakt = $this->stuckDrafts($since)
            ->whereNull('draft_reminded_at')
            ->whereNull('photo_bug_notified_at')
            ->count();

        if ($onaangeraakt > 0) {
            $signalen[] = sprintf(
                '%d concept(en) blijven hangen zonder foto — vaak het teken dat iemand vastliep bij het uploaden.',
                $onaangeraakt,
            );
        }

        // Een claim-link is 30 dagen geldig (DealService::CLAIM_DAYS), ruim
        // langer dan `silence_days`. Alarmeren op `created_at` zou dus elke
        // normale, nog niet geclaimde verkoop vanaf dag 7 laten afgaan.
        // Het signaal moet daarom op de vervaldatum van de link zitten: pas
        // als die verstreken is zonder claim, is er echt iets aan de hand.
        // Legacy-rijen zonder claim-token (van vóór de claim-link) hebben
        // geen vervaldatum en vallen terug op de oude `created_at`-regel.
        $vergeten = Transaction::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($now, $quiet) {
                $query->where('claim_expires_at', '<=', $now)
                    ->orWhere(function ($query) use ($quiet) {
                        $query->whereNull('claim_expires_at')->where('created_at', '<=', $quiet);
                    });
            })
            ->count();
        if ($vergeten > 0) {
            $signalen[] = sprintf('%d deal(s) wachten nog op bevestiging terwijl er geen bruikbare claim-link meer is — de verkoper kan een nieuwe sturen.', $vergeten);
        }

        // Weigeren is onomkeerbaar: de advertentie blijft op 'sold' staan en
        // geen enkel scherm toont een 'cancelled'-rij. Zonder dit signaal ziet
        // de verkoper nergens dat zijn verkoop stilletjes is afgeketst.
        //
        // `updated_at` is hier wel veilig, in tegenstelling tot bij
        // `deals_bevestigd` hierboven: claim(), decline() en
        // refreshClaimToken() weigeren alle drie een status die niet
        // 'pending' is, dus een 'cancelled'-rij wordt na het weigeren nooit
        // meer aangeraakt.
        $geweigerd = Transaction::query()->where('status', 'cancelled')->where('updated_at', '>=', $since)->count();
        if ($geweigerd > 0) {
            $signalen[] = sprintf('%d deal(s) geweigerd door de koper in de laatste 24 uur — de advertentie blijft op verkocht staan.', $geweigerd);
        }

        // Beveiligingsadviezen staan bewust in `signalen` en niet in `cijfers`:
        // het antwoord kan "onbekend" zijn, en dat is geen getal. Een nul die
        // in werkelijkheid "de controle is stuk" betekent, is het gevaarlijkste
        // antwoord dat deze mail kan geven.
        // Rob Turks melding bleef 29 dagen liggen en hij zegde er zijn account
        // om op, met als zwaarste verwijt dat er nergens stond dat hier 1
        // onbetaalde maintainer zit. Meldingen op het platform komen sinds
        // 22-08 hier langs; issues niet, en dat was het gat waar hij in
        // verdween. Dit is bewust voorraad en geen aanwas: antwoorden maakt het
        // stil, en die knop ligt bij ons.
        $onbeantwoord = $this->issues->find();
        if ($onbeantwoord === null) {
            $signalen[] = 'Kon GitHub niet bereiken, dus geen uitspraak over onbeantwoorde issues.';
        } else {
            foreach ($onbeantwoord as $issue) {
                $signalen[] = sprintf(
                    'Issue #%d ligt %d dagen zonder antwoord: %s',
                    $issue['number'],
                    $issue['days'],
                    $issue['title'],
                );
            }
        }

        $adviezen = config('cloudmarktplaats.ops.audit_check')
            ? $this->advisories->count()
            : 0;
        if ($adviezen === null) {
            $signalen[] = 'Kon `composer audit` niet draaien — geen uitspraak over beveiligingsadviezen in de afhankelijkheden.';
        } elseif ($adviezen > 0) {
            $signalen[] = sprintf(
                '%d beveiligingsadvies(en) in de afhankelijkheden. Draai `composer audit` voor de details.',
                $adviezen,
            );
        }

        return ['cijfers' => $cijfers, 'fouten' => $fouten, 'signalen' => $signalen];
    }

    /**
     * Concepten zonder foto die minstens 24 uur stilliggen.
     *
     * Ouder dan `$since`, want wie nu bezig is met invullen is geen signaal.
     *
     * @return Builder<Listing>
     */
    private function stuckDrafts(Carbon $since)
    {
        return Listing::query()
            ->where('state', 'draft')
            ->where('updated_at', '<=', $since)
            ->whereDoesntHave('photos');
    }

    /**
     * Foutregels uit de staart van de log, gegroepeerd op boodschap.
     *
     * @return list<array{aantal: int, regel: string}>
     */
    private function errorsSince(Carbon $since): array
    {
        $path = (string) config('cloudmarktplaats.ops.log_path');
        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        // Alleen de staart: de log groeit tot megabytes en de hele bak inlezen
        // om 24 uur te bekijken is verspilling.
        $size = (int) filesize($path);
        if ($size > self::LOG_TAIL_BYTES) {
            fseek($handle, -self::LOG_TAIL_BYTES, SEEK_END);
            fgets($handle); // halve eerste regel weggooien
        }

        $counts = [];
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $line, $m) !== 1) {
                continue;
            }
            if (Carbon::parse($m[1])->lt($since)) {
                continue;
            }
            // Stacktraces en context afkappen: de boodschap is het signaal.
            $message = trim(mb_substr(explode(' {"', $m[3])[0], 0, 160));
            $key = $m[2].': '.$message;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        fclose($handle);

        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, 10, true) as $regel => $aantal) {
            $out[] = ['aantal' => $aantal, 'regel' => $regel];
        }

        return $out;
    }
}
