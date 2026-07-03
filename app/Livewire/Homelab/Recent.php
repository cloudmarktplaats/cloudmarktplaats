<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Models\HomelabPost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Recent extends Component
{
    #[Locked]
    public int $limit = 3;

    /**
     * @return Collection<int, HomelabPost>
     */
    public function posts(): Collection
    {
        if (! config('cloudmarktplaats.features.homelab_feed')) {
            return new Collection;
        }

        return HomelabPost::query()
            ->published()
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.homelab.recent', ['posts' => $this->posts()]);
    }
}
