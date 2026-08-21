<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Exceptions\DealException;
use App\Models\Transaction;
use App\Services\Gamification\DealService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mijn deals" — het eigen handelsverleden.
 *
 * Bevestigen gebeurt sinds de claim-link op /deal/{token}: een `pending`-rij
 * heeft daarom geen koper meer, en de oude lijst zou permanent leeg staan.
 * Deze pagina toont daarom afgeronde deals in beide rollen. `pending()` en
 * `confirm()` blijven staan voor rijen van vóór die wijziging — die hebben
 * wél een koper en verdienen nog steeds hun knop.
 */
#[Layout('components.layouts.marketing', ['title' => 'Mijn deals — Cloudmarktplaats'])]
class Deals extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 404);
    }

    public function confirm(int $id): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 403);

        $tx = Transaction::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user !== null && $tx->buyer_user_id === $user->id, 403);

        try {
            app(DealService::class)->confirm($tx, $user);
        } catch (DealException $e) {
            $this->addError('deal', $e->getMessage());
        }
    }

    /** @return Collection<int, Transaction> */
    public function pending(): Collection
    {
        return Transaction::query()
            ->where('buyer_user_id', (int) auth()->id())
            ->where('status', 'pending')
            ->with('listing')
            ->latest()
            ->get();
    }

    /** @return Collection<int, Transaction> */
    public function confirmed(): Collection
    {
        $id = (int) auth()->id();

        return Transaction::query()
            ->where('status', 'completed')
            ->where(fn ($q) => $q->where('buyer_user_id', $id)->orWhere('seller_user_id', $id))
            ->with('listing')
            ->latest('completed_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.profile.deals', [
            'pending' => $this->pending(),
            'confirmed' => $this->confirmed(),
        ]);
    }
}
