<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailSubscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * De enige plek waar inschrijvingen ontstaan, bevestigd worden en verdwijnen.
 *
 * Toestemming moet aantoonbaar zijn (art. 7 lid 1 AVG) en dit platform bewaart
 * geen IP's, dus het bewijs bestaat uit de letterlijke zin waarop iemand ja
 * zei plus de bevestigingsklik uit zijn eigen mailbox.
 *
 * Daaruit volgt de harde regel van deze service: een bevestigde inschrijving
 * wordt nooit aangeraakt door iemand die niet kan aantonen dat het adres van
 * hem is. Het aanmeldformulier is publiek, dus anders kan een vreemde het
 * bewijs van een ander overschrijven en de rij bovendien terugzetten naar
 * onbevestigd — waarna `purgeUnconfirmed()` hem diezelfde nacht opruimt.
 */
class MailSubscriptionService
{
    /**
     * Precies de sleutels die `subscribe()` in `$wanted` zet, en dus de enige
     * die ooit legitiem in `pending_changes` mogen staan. `confirm()` filtert
     * hierop voordat hij het vak in de rij terugschrijft.
     *
     * @var list<string>
     */
    private const PENDING_FIELDS = [
        'wants_offers', 'wants_updates', 'categories',
        'consent_text', 'consent_given_at', 'consent_source',
    ];

    /**
     * Vier gevallen, in deze volgorde:
     *
     * 1. Geen rij: aanmaken, onbevestigd.
     * 2. Rij bestaat maar is onbevestigd: bijwerken, blijft onbevestigd. Er is
     *    nog geen bewijs, dus er valt ook niets te beschermen.
     * 3. De aanroeper is de bewezen eigenaar van dit adres (ingelogd,
     *    geverifieerd, en `$user->email` is precies dit adres): meteen
     *    bijwerken en bevestigd zetten.
     * 4. Rij bestaat, is bevestigd, en de aanroeper is niet die eigenaar: de
     *    rij blijft zoals hij is. De gevraagde wijziging wordt geparkeerd in
     *    `pending_changes` met een vers `confirm_token`; `confirm()` past hem
     *    pas toe als er op de link in díé mailbox is geklikt. Staat er een
     *    `unsubscribed_at`, dan wordt er zelfs niet geparkeerd: zie `write()`.
     *
     * @param  list<string>  $categories
     */
    public function subscribe(
        string $email,
        bool $wantsOffers,
        bool $wantsUpdates,
        array $categories,
        string $consentText,
        string $source,
        ?User $user = null,
    ): MailSubscription {
        $normalizedEmail = self::normalise($email);

        // `email_verified_at` bewijst één mailbox: die van het account zelf.
        // Zonder de vergelijking met het adres van het account zou elk ingelogd
        // lid een willekeurig adres direct op bevestigd kunnen zetten.
        // Genormaliseerd vergelijken, want zo staat het ook in de tabel.
        $owner = $user !== null
            && $user->email_verified_at !== null
            && self::normalise($user->email) === $normalizedEmail
            ? $user
            : null;

        $wanted = [
            'wants_offers' => $wantsOffers,
            'wants_updates' => $wantsUpdates,
            'categories' => array_values($categories),
            'consent_text' => $consentText,
            'consent_given_at' => now(),
            'consent_source' => $source,
        ];

        try {
            return DB::transaction(fn () => $this->write($normalizedEmail, $wanted, $owner));
        } catch (UniqueConstraintViolationException) {
            // Twee bezoekers die op hetzelfde moment hetzelfde verse adres
            // invoeren, lezen allebei "bestaat nog niet" en proberen allebei te
            // inserten. De unieke index laat er één door; de ander zou een 500
            // op een publiek formulier geven. Eén herkansing is genoeg: de rij
            // bestaat nu wel, dus de tweede poging werkt hem gewoon bij.
            return DB::transaction(fn () => $this->write($normalizedEmail, $wanted, $owner));
        }
    }

    public function confirm(string $token): ?MailSubscription
    {
        $sub = MailSubscription::query()->where('confirm_token', $token)->first();

        if ($sub === null) {
            return null;
        }

        // De klik komt uit de mailbox zelf en is daarmee het bewijs dat een
        // geparkeerde wijziging mocht. Het vak moet daarna leeg, anders past een
        // volgende bevestiging diezelfde wijziging nog een keer toe.
        //
        // `array_intersect_key` filtert op de velden die `subscribe()` er ooit
        // in zet (zie `$wanted`). Vandaag komt `pending_changes` alleen uit de
        // service zelf, dus dit filter verandert nu niets, maar zonder filter
        // is dit `forceFill(array_merge(...))` mass-assignment op `email`,
        // `user_id` en `confirmed_at` zodra dat vak ooit een ander veld bevat.
        $pending = is_array($sub->pending_changes)
            ? array_intersect_key($sub->pending_changes, array_flip(self::PENDING_FIELDS))
            : [];

        // Een toegepaste geparkeerde wijziging draagt een verse `consent_text`
        // en `consent_given_at`, bewezen door de klik uit die mailbox: dat is
        // een nieuwe toestemming, dus een eerdere intrekking is geschiedenis.
        // Een kale dubbele opt-in (leeg vak) zegt niets nieuws over toestemming
        // en laat dat moment daarom staan.
        if ($pending !== []) {
            $pending['unsubscribed_at'] = null;
        }

        $sub->forceFill(array_merge($pending, [
            'confirmed_at' => now(),
            'confirm_token' => null,
            'pending_changes' => null,
        ]))->save();

        return $sub;
    }

    /** @param  string|null  $what  'offers', 'updates', of null voor alles. */
    public function unsubscribe(string $token, ?string $what = null): ?MailSubscription
    {
        // Een onbekende waarde komt uit een afmeldlink die wij zelf opstellen,
        // dus is het een fout van ons en geen keuze van de bezoeker. Stil alles
        // afmelden zou die fout verbergen én meer uitzetten dan gevraagd, en dat
        // merk je pas als iemand klaagt dat hij niets meer krijgt. Liever hard
        // stuk in de tests dan stil verkeerd in productie; de route die dit
        // aanroept hoort de parameter dus zelf te valideren.
        if ($what !== null && ! in_array($what, ['offers', 'updates'], true)) {
            throw new InvalidArgumentException("Onbekend afmelddoel: {$what}");
        }

        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();

        // Omgekeerd te lezen: het gevraagde doel wordt altijd `false`, en het
        // andere doel houdt zijn huidige waarde doordat de vergelijking daar
        // waar is. Bij `$what === null` zijn beide vergelijkingen onwaar en
        // gaan dus beide uit. De `&&`-bewaking is er zodat afmelden nooit iets
        // aanzet dat al uit stond — mail sturen ná een afmelding is precies wat
        // niet mag.
        $sub?->forceFill([
            'wants_offers' => $what === 'updates' && $sub->wants_offers,
            'wants_updates' => $what === 'offers' && $sub->wants_updates,
            // Afmelden trekt de toestemming voor dit adres in, dus alles wat er
            // voor dit adres nog in de wachtrij staat vervalt mee. Zonder deze
            // regel blijft een door een vreemde geparkeerde wijziging (geval 4
            // in `write()`) staan met een levend `confirm_token`, en zet één
            // klik op die oudere bevestigingslink de zojuist afgemelde
            // voorkeuren weer aan.
            'pending_changes' => null,
            // Het bewijs van de oude toestemming blijft staan, want dat is wat
            // er destijds is afgesproken. Zonder dit moment ernaast wijst dat
            // bewijs naar een toestemming die niet meer bestaat en is nergens
            // vastgelegd dat iemand nee zei. Ook een half afmelden (`$what`)
            // zet hem: dat is het intrekken van een echte toestemming, en dit
            // is de enige plek waar dat een spoor achterlaat.
            'unsubscribed_at' => now(),
            // Alleen bij een bevestigde rij hoort dat token bij die wachtrij.
            // Op een nog onbevestigde rij is het de gewone dubbele opt-in uit
            // de aanmeldmail; dat wissen zou die link slopen en de aanmelding
            // onafmaakbaar maken.
            'confirm_token' => $sub->confirmed_at !== null ? null : $sub->confirm_token,
        ])->save();

        return $sub;
    }

    /**
     * De ongedaan-maken-knop op het afmeldscherm: zet één soort mail (of allebei)
     * weer aan voor het adres achter dit afmeldtoken.
     *
     * Dit is een nieuwe toestemming, geen voortzetting van de oude: die was
     * zojuist ingetrokken. Zou `consent_text` naar het oude moment blijven
     * wijzen, dan bewijst het vak een toestemming die op dat moment niet meer
     * bestond, en dat is onder art. 7 lid 1 AVG erger dan geen bewijs.
     *
     * @param  string|null  $what  'offers', 'updates', of null voor allebei.
     */
    public function resubscribe(string $token, ?string $what = null): ?MailSubscription
    {
        // Symmetrisch met `unsubscribe()`: onzin is een fout in de link die wij
        // zelf versturen, geen keuze van de bezoeker. Stil "Hersteld" melden
        // terwijl er niets hersteld is, verbergt die fout.
        if ($what !== null && ! in_array($what, ['offers', 'updates'], true)) {
            throw new InvalidArgumentException("Onbekend hersteldoel: {$what}");
        }

        $sub = MailSubscription::query()->where('unsubscribe_token', $token)->first();

        // Zie `write()`: gaat het aanbod van uit naar aan, dan begint het
        // venster van de aanbodmail opnieuw. Hier is dat het duidelijkst
        // zichtbaar — zonder deze regel kreeg wie na twee maanden op de
        // herstelknop drukte die twee maanden in 1 mail.
        $vensterOpnieuw = $sub !== null
            && $what !== 'updates'
            && $sub->wants_offers !== true
            && $sub->offers_sent_at !== null;

        // Spiegelbeeld van `unsubscribe()`: het gevraagde doel gaat aan, het
        // andere houdt zijn huidige waarde. Bij `null` gaan beide aan.
        $sub?->forceFill([
            'wants_offers' => $sub->wants_offers || $what !== 'updates',
            'offers_sent_at' => $vensterOpnieuw ? now() : $sub->offers_sent_at,
            'wants_updates' => $sub->wants_updates || $what !== 'offers',
            'consent_text' => match ($what) {
                'offers' => 'Toch nieuw aanbod, hersteld na een afmelding.',
                'updates' => 'Toch updates, hersteld na een afmelding.',
                default => 'Toch mail, hersteld na een afmelding.',
            },
            'consent_given_at' => now(),
            'consent_source' => 'herstelknop',
            // Er ligt een verse toestemming, dus de intrekking ervoor is
            // geschiedenis en niet meer de stand van nu.
            'unsubscribed_at' => null,
        ])->save();

        return $sub;
    }

    /**
     * Koppelt een losse inschrijving aan het account dat later met dat adres
     * registreert. Alleen rijen zonder koppeling komen in aanmerking: een rij
     * die al aan een ander account hangt, hoort daar te blijven hangen.
     *
     * Aanroepen hoort bij het bewijzen van het adres (het `Verified`-event),
     * niet bij het aanmaken van het account. Een vers account is namelijk niet
     * meer dan een claim op een adres, en de wiscascade op `user_id` maakt die
     * koppeling gevaarlijk: koppelen op een claim geeft een vreemde die op jouw
     * adres registreert jouw bevestigde inschrijving in handen, en hij gooit hem
     * weg zodra hij zijn eigen account laat wissen.
     *
     * De toets staat hier en niet alleen bij de aanroepers, want dit is de
     * laatste plek waar een pad dat het vergeet nog tegengehouden kan worden.
     * Zelfde maatstaf als in `subscribe()`: `email_verified_at` is het bewijs.
     */
    public function linkToUser(User $user): void
    {
        if ($user->email_verified_at === null) {
            return;
        }

        MailSubscription::query()
            ->whereNull('user_id')
            ->where('email', self::normalise((string) $user->email))
            ->update(['user_id' => $user->id]);
    }

    /** Onbevestigde aanmeldingen zijn geen toestemming, dus die blijven niet staan. */
    public function purgeUnconfirmed(int $days = 7): int
    {
        return MailSubscription::query()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Schrijft de vier gevallen weg. `$owner` is alleen gevuld als de aanroeper
     * bewezen eigenaar van dit adres is.
     *
     * @param  array<string, mixed>  $wanted
     */
    private function write(string $email, array $wanted, ?User $owner): MailSubscription
    {
        $sub = MailSubscription::query()->where('email', $email)->first();

        // Geval 4.
        if ($sub !== null && $sub->confirmed_at !== null && $owner === null) {
            // Wie zich afmeldde heeft nee gezegd, en dat is vastgelegd. Parkeren
            // zet een vers `confirm_token`, en dat token is precies wat er een
            // bevestigingsmail naar die mailbox laat gaan: een vreemde die het
            // adres op het publieke formulier intikt, mailt zo iemand die
            // uitdrukkelijk afscheid nam. De privacyverklaring belooft "meld je
            // je af, dan stopt de mail meteen"; die belofte hoort hier te staan
            // en niet alleen in dat document.
            //
            // De rij blijft dus onaangeraakt: geen wachtrij, geen token, geen
            // opgeschoven `updated_at`. `MailSubscriptionConfirmMail::send()`
            // ziet dan een leeg `confirm_token` en verstuurt niets. Terugkomen
            // kan nog steeds langs de twee wegen die de mailbox wél bewijzen:
            // de herstelknop in de laatste mail (`resubscribe()`), en het
            // profiel van een lid met een geverifieerd adres (geval 3).
            if ($sub->unsubscribed_at !== null) {
                return $sub;
            }

            $sub->forceFill([
                'pending_changes' => $wanted,
                'confirm_token' => Str::random(48),
            ])->save();

            return $sub;
        }

        // Gevallen 1 tot en met 3.
        //
        // `SendOfferDigest` rekent "nieuw" vanaf `offers_sent_at ?? created_at`.
        // Die stempel blijft na een afmelding staan op de laatste verzending, en
        // niemand zette hem ooit terug. Wie opnieuw ja zegt tegen aanbod kreeg
        // daardoor in 1 keer alles wat er sinds die dag bij kwam: de reviewer mat
        // 20 advertenties na een profielwijziging. Dat is de catalogus die het
        // commando in zijn eigen docblock zegt niet te sturen.
        //
        // Alleen bij de overgang van uit naar aan, want alleen dan is er een
        // nieuwe toestemming voor aanbod. Wie zijn categorieën bijstelt terwijl
        // het aanbod al aan stond, hoort de advertenties van deze week gewoon te
        // krijgen; het venster daar verzetten laat een week stil verdwijnen.
        // En alleen als er echt een oude stempel staat: zonder stempel is het
        // ijkpunt `created_at`, en dat wordt hieronder toch al ververst.
        $vensterOpnieuw = $sub !== null
            && $wanted['wants_offers'] === true
            && $sub->wants_offers !== true
            && $sub->offers_sent_at !== null;

        $sub ??= new MailSubscription;

        $sub->forceFill(array_merge($wanted, [
            'email' => $email,
            // `purgeUnconfirmed()` rekent vanaf `created_at`. Hier wordt de
            // toestemming echt ververst (nieuwe rij, of een niet-bewezen
            // aanmelder die opnieuw ja zegt op een onbevestigde rij, of de
            // bewezen eigenaar), dus het opruimvenster hoort vanaf nu te
            // lopen, niet vanaf de eerste, misschien in spam beland aanmelding.
            'created_at' => now(),
            // Dit token zit al in elke verstuurde mail; een nieuw token zou de
            // afmeldlink daarin met terugwerkende kracht breken.
            'unsubscribe_token' => $sub->unsubscribe_token ?? Str::random(48),
            // Een bestaande koppeling met een account blijft staan: die draagt
            // de wisverplichting uit taak 1. Er komt er alleen een bij als het
            // adres bewezen van dat account is.
            'user_id' => $owner?->id ?? $sub->user_id,
            // Het ijkpunt van de aanbodmail; zie de toelichting hierboven. In
            // dezelfde save en niet los via `DB::table()`, want hier verandert
            // de rij echt en hoort `updated_at` dus wél mee te schuiven.
            'offers_sent_at' => $vensterOpnieuw ? now() : $sub->offers_sent_at,
            'confirmed_at' => $owner !== null ? now() : $sub->confirmed_at,
            'confirm_token' => $owner !== null ? null : Str::random(48),
            'pending_changes' => null,
            // Hier ligt een verse toestemming (`$wanted` draagt een nieuwe
            // `consent_text` en `consent_given_at`), dus een eerdere intrekking
            // is geschiedenis. Geval 4 komt hier niet: een geparkeerde
            // wijziging van een vreemde is geen toestemming van de eigenaar en
            // laat het moment van intrekking dus staan.
            'unsubscribed_at' => null,
        ]))->save();

        return $sub;
    }

    private static function normalise(string $email): string
    {
        return Str::lower(trim($email));
    }
}
