<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Mail\MailSubscriptionConfirmMail;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publiek aanmeldformulier. Geen account nodig.
 *
 * De vlagcontrole staat in boot() en niet in mount(): mount() draait alleen bij
 * de eerste page load, dus een pagina die al openstond klikt anders gewoon door
 * nadat de vlag om is. Die val staat vast in de test "refuses a save from a page
 * that was already open when the flag went off"; met de controle in mount()
 * levert die test een 200 op in plaats van een 404.
 *
 * Tegen geautomatiseerd rondstrooien staan hier dezelfde drie vangnetten als in
 * ContactSeller: een honeypot, een tijdklem en twee emmers (zie
 * passesRateLimit()).
 */
#[Layout('components.layouts.marketing', ['title' => 'Nieuwsbrief — Cloudmarktplaats'])]
class Subscribe extends Component
{
    public string $email = '';

    public bool $wants_offers = false;

    public bool $wants_updates = false;

    /** @var list<string> */
    public array $categories = [];

    /** Honeypot. Moet leeg blijven; een mens ziet dit veld nooit. */
    public string $website = '';

    /** Unix-tijd van het renderen, voor de tijdklem. */
    public int $formLoadedAt = 0;

    public bool $done = false;

    public const CONSENT_OFFERS = 'Ja, mail mij nieuw aanbod in de categorieen die ik aanvink. Ik kan me altijd weer afmelden.';

    public const CONSENT_UPDATES = 'Ja, stuur mij updates over het platform. Ik kan me altijd weer afmelden.';

    /**
     * De 12 hoofdcategorieen uit CategorySeeder. Bewust alleen het bovenste
     * niveau: op een lijst van een paar honderd abonnees is fijnmaziger
     * selecteren een filter dat vrijwel alles wegfiltert.
     *
     * Deze lijst is ook de validatie, want wat hier binnenkomt bepaalt straks
     * wie welke mail krijgt en dat mag geen willekeurige tekst uit een request
     * zijn.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'compute', 'networking', 'servers', 'storage', 'av', 'power',
        'kabels', 'fabrication', 'books', 'licenses', 'meet', 'misc',
    ];

    /**
     * De 12 labels die op het scherm staan. Ook de bevestigingsmail leest ze
     * hier, want de ontvanger hoort te herkennen wat de aanvrager aanvinkte; die
     * zag labels, geen slugs.
     *
     * Een methode en geen constante, omdat `__()` niet in een constante past.
     *
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'compute' => __('Compute'),
            'networking' => __('Networking'),
            'servers' => __('Server hardware'),
            'storage' => __('Storage'),
            'av' => __('Audio/Video pro'),
            'power' => __('Power'),
            'kabels' => __('Kabels & connectoren'),
            'fabrication' => __('3D printers & CNC'),
            'books' => __('Boeken & documentatie'),
            'licenses' => __('Software licenties'),
            'meet' => __('Meetapparatuur'),
            'misc' => __('Overig'),
        ];
    }

    public function boot(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.mail_list'), 404);
    }

    public function mount(): void
    {
        $this->formLoadedAt = now()->getTimestamp();
    }

    public function save(): void
    {
        // Honeypot ingevuld of binnen 2 seconden verzonden: geen mens. Zelfde
        // stille afhandeling als ContactSeller, inclusief het succesbeeld, zodat
        // een scraper de val niet leert kennen.
        if ($this->website !== '' || now()->getTimestamp() - $this->formLoadedAt < 2) {
            $this->done = true;

            return;
        }

        $this->validate([
            'email' => ['required', 'email'],
            // Zonder vinkje is er geen toestemming, en dan is er niets om vast
            // te leggen. Niet `required_without_all`: een uitgevinkt vakje is
            // `false` en dat is voor `required` gewoon aanwezig, dus die regel
            // laat twee lege vinkjes er stil doorheen.
            'wants_offers' => ['accepted_if:wants_updates,false', 'boolean'],
            'categories' => [$this->wants_offers ? 'required' : 'nullable', 'array'],
            'categories.*' => ['string', Rule::in(self::CATEGORIES)],
        ], [
            'wants_offers.accepted_if' => __('Vink aan waar je mail over wilt krijgen.'),
            'categories.required' => __('Kies minstens 1 categorie.'),
            // Zonder eigen tekst wordt dit "De gekozen categories.1 is ongeldig":
            // dat lekt de attribuutnaam en zegt de bezoeker niets.
            'categories.*.in' => __('Kies alleen categorieën uit de lijst.'),
        ]);

        if (! $this->passesRateLimit()) {
            return;
        }

        $sub = app(MailSubscriptionService::class)->subscribe(
            email: $this->email,
            wantsOffers: $this->wants_offers,
            wantsUpdates: $this->wants_updates,
            categories: $this->categories,
            consentText: $this->consentText(),
            source: 'formulier',
        );

        Mail::to($sub->email)->queue(new MailSubscriptionConfirmMail($sub));

        $this->done = true;
    }

    /**
     * Het formulier is publiek en stuurt mail naar een adres dat de invuller
     * niet hoeft te bezitten. Bij een al bevestigd adres parkeert de service
     * bovendien een wijziging met een vers token, en daar zit geen
     * vervaltermijn op: de service kan herhaling dus niet zelf zien.
     *
     * De rem zit daarom hier, in dezelfde vorm als ContactSeller: 10 pogingen
     * per IP per uur, en 3 per adres per dag.
     *
     * De harde grens is die per IP. De emmer per adres is letterlijk: hij kijkt
     * naar de ingetikte tekst, niet naar de mailbox erachter. Wie puntjes of een
     * plus-tag in een gmailadres varieert, krijgt gewoon een verse emmer, en
     * dezelfde fysieke mailbox kan dus meer dan 3 van deze mails per dag
     * ontvangen. Providerspecifiek normaliseren lost dat niet op: bij veel
     * andere providers zijn diezelfde puntjes wél betekenisvol, en dan zou de
     * rem adressen op één hoop gooien die niet van dezelfde persoon zijn. Die
     * emmer is dus best-effort tegen herhaald intikken van hetzelfde adres; de
     * grens waar een aanvaller niet omheen kan zonder ook van IP te wisselen, is
     * de emmer per IP. Daarnaast staan hierboven de honeypot en de tijdklem.
     *
     * Het IP wordt gehasht en niet opgeslagen; de sleutel vervalt met de emmer.
     * Dat hoort bij de belofte dat dit platform geen IP's bewaart.
     */
    private function passesRateLimit(): bool
    {
        $pepper = (string) config('app.key');
        $perIp = 'mail-subscribe:ip:'.hash('sha256', (string) request()->ip().$pepper);
        $perEmail = 'mail-subscribe:email:'.hash('sha256', Str::lower(trim($this->email)).$pepper);

        if (RateLimiter::tooManyAttempts($perIp, 10) || RateLimiter::tooManyAttempts($perEmail, 3)) {
            $this->addError('email', __('Er zijn al genoeg mails naar dit adres onderweg. Probeer het later opnieuw.'));

            return false;
        }

        RateLimiter::hit($perIp, 3600);
        RateLimiter::hit($perEmail, 86400);

        return true;
    }

    /** De letterlijke zin die op het scherm stond, want dat is het bewijs. */
    private function consentText(): string
    {
        return trim(
            ($this->wants_offers ? self::CONSENT_OFFERS.' ' : '')
            .($this->wants_updates ? self::CONSENT_UPDATES : '')
        );
    }

    public function render(): View
    {
        return view('livewire.mail.subscribe');
    }
}
