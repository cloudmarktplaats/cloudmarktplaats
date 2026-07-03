<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Exceptions\InviteException;
use App\Models\InviteCode;
use App\Models\User;
use App\Services\Gamification\InviteService;
use App\Services\Gamification\KarmaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.marketing', ['title' => 'Uitnodigingen — Cloudmarktplaats'])]
class Invites extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.invites'), 404);
    }

    public function generate(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            app(InviteService::class)->generate($user);
        } catch (InviteException $e) {
            $this->addError('generate', $e->getMessage());
        }
    }

    /** @return Collection<int, InviteCode> */
    public function codes(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->invitesSent()->latest()->get();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.profile.invites', [
            'karma' => app(KarmaService::class)->karmaFor($user),
            'credits' => (int) $user->invite_credits,
            'codes' => $this->codes(),
        ]);
    }
}
