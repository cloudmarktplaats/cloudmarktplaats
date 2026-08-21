<?php

declare(strict_types=1);

namespace App\Livewire\Deals;

use App\Exceptions\DealException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\DealService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * De koperskant van een gemelde verkoop.
 *
 * Deze pagina wordt koud geopend via een link die de verkoper zelf in zijn
 * antwoordmail heeft geplakt — wij kennen het adres van de koper niet en
 * kunnen hem dus niet mailen (`contact_relay_logs` bewaart bewust alleen
 * listing_id + tijdstip). De pagina is publiek bereikbaar, maar bevestigen en
 * weigeren vereisen een geverifieerd account: dezelfde lat als bij invites.
 */
#[Layout('components.layouts.marketing', ['title' => 'Deal bevestigen — Cloudmarktplaats'])]
class Claim extends Component
{
    public string $token = '';

    /** '' zolang er nog een keuze te maken valt, daarna 'confirmed' of 'declined'. */
    public string $done = '';

    public function mount(string $token): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 404);

        $this->token = $token;

        // Zowel een gast als een ingelogde gebruiker zonder geverifieerd
        // e-mailadres kan hier nog niets: parkeer de URL zodat inloggen,
        // registreren én verifiëren hem op deze pagina terugzetten.
        $user = auth()->user();
        if ($user === null || ! $user->hasVerifiedEmail()) {
            session()->put('url.intended', route('deals.claim', ['token' => $token]));
        }
    }

    public function confirm(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 403);

        try {
            app(DealService::class)->claim($this->token, $this->verifiedUser());
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());

            return;
        }

        $this->done = 'confirmed';
    }

    public function decline(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 403);

        try {
            app(DealService::class)->decline($this->token, $this->verifiedUser());
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());

            return;
        }

        $this->done = 'declined';
    }

    private function verifiedUser(): User
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->hasVerifiedEmail(), 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.deals.claim', [
            'transaction' => Transaction::query()
                ->with(['listing', 'seller'])
                ->where('claim_token', $this->token)
                ->first(),
        ]);
    }
}
