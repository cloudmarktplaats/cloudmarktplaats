<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Mail\Subscribe;
use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Mail\MailSubscriptionService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dezelfde twee vinkjes en dezelfde 12 categorieen als het publieke
 * aanmeldformulier (Subscribe), maar dan voor een lid dat al is ingelogd.
 * mount() leest de bestaande inschrijving van dit account in, save() slaat op
 * via dezelfde MailSubscriptionService — met de ingelogde gebruiker erbij,
 * want alleen dan zet subscribe() de rij meteen op bevestigd (e-mailverificatie
 * heeft de mailbox al bewezen; zonder dat argument zou elke wijziging hier
 * onnodig in de wachtrij van pending_changes belanden).
 *
 * De vlagcontrole staat in boot() en niet in mount(), om dezelfde reden als bij
 * Subscribe: mount() draait niet meer op een tabblad dat al openstond toen de
 * vlag omging, boot() wel bij elke request. Dit component hoort achter
 * dezelfde vlag als het aanmeldformulier: mail_list is een noodrem voor de
 * hele mailinglijst, niet alleen voor nieuwe aanmeldingen op het publieke
 * formulier. Staat de vlag uit, dan hoort een lid hier ook niet meer aan te
 * kunnen draaien.
 *
 * Zet een lid zijn laatste vinkje uit, dan is dat een echte afmelding en geen
 * "toestemming voor niets": save() roept dan unsubscribe() aan in plaats van
 * subscribe(). subscribe() zou met twee lege vinkjes een consent_text van ''
 * wegschrijven en zo het bestaande bewijs van toestemming overschrijven met
 * bewijs van niets, terwijl unsubscribe() dat bewijs juist laat staan en
 * alleen de voorkeuren en een eventuele wachtrij opruimt.
 */
#[Layout('components.layouts.marketing', ['title' => 'Mailvoorkeuren — Cloudmarktplaats'])]
class MailPreferences extends Component
{
    public bool $wants_offers = false;

    public bool $wants_updates = false;

    /** @var list<string> */
    public array $categories = [];

    public bool $saved = false;

    public function boot(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.mail_list'), 404);
    }

    public function mount(): void
    {
        $sub = $this->existingSubscription();

        if ($sub === null) {
            return;
        }

        $this->wants_offers = $sub->wants_offers;
        $this->wants_updates = $sub->wants_updates;
        $this->categories = array_values(array_map('strval', (array) $sub->categories));
    }

    public function save(): void
    {
        $this->saved = false;

        $this->validate([
            'categories' => [$this->wants_offers ? 'required' : 'nullable', 'array'],
            'categories.*' => ['string', Rule::in(Subscribe::CATEGORIES)],
        ], [
            'categories.required' => __('Kies minstens 1 categorie.'),
            'categories.*.in' => __('Kies alleen categorieën uit de lijst.'),
        ]);

        // Geen aanbodmail, dan zeggen die categorieen niets meer. Laten staan
        // zou de rij iets anders laten zeggen dan wat de bezoeker aanvinkte.
        if (! $this->wants_offers) {
            $this->categories = [];
        }

        if (! $this->wants_offers && ! $this->wants_updates) {
            $this->unsubscribeExisting();
            $this->saved = true;

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        app(MailSubscriptionService::class)->subscribe(
            email: $user->email,
            wantsOffers: $this->wants_offers,
            wantsUpdates: $this->wants_updates,
            categories: $this->categories,
            consentText: $this->consentText(),
            source: 'profiel',
            user: $user,
        );

        $this->saved = true;
    }

    /**
     * Genormaliseerd zoeken, want zo staat het adres in de tabel. Vertrouwen op
     * de mutator van User zou deze afmeldtak laten afhangen van code elders:
     * sneuvelt die mutator, dan vindt dit de rij niet meer en is een uitgezet
     * vinkje stil geen afmelding.
     */
    private function existingSubscription(): ?MailSubscription
    {
        /** @var User $user */
        $user = auth()->user();

        return MailSubscription::query()
            ->where('email', Str::lower(trim((string) $user->email)))
            ->first();
    }

    /** Geen inschrijving, geen afmelding: er is dan niets om in te trekken. */
    private function unsubscribeExisting(): void
    {
        $sub = $this->existingSubscription();

        if ($sub !== null) {
            app(MailSubscriptionService::class)->unsubscribe((string) $sub->unsubscribe_token);
        }
    }

    /** Zelfde zinnen als op het aanmeldformulier, want dat is wat hier ook op het scherm staat. */
    private function consentText(): string
    {
        return trim(
            ($this->wants_offers ? Subscribe::CONSENT_OFFERS.' ' : '')
            .($this->wants_updates ? Subscribe::CONSENT_UPDATES : '')
        );
    }

    public function render(): View
    {
        return view('livewire.profile.mail-preferences');
    }
}
