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

#[Layout('components.layouts.marketing', ['title' => 'Mijn deals — Cloudmarktplaats'])]
class Deals extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.deals'), 404);
    }

    public function confirm(int $id): void
    {
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

    public function render(): View
    {
        return view('livewire.profile.deals', ['pending' => $this->pending()]);
    }
}
