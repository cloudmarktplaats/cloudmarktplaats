<?php

declare(strict_types=1);

namespace App\Livewire\News;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publieke nieuws-reader. Anoniem: alle actieve bronnen. Ingelogd: de
 * aangevinkte bronnen, met gelezen-watermerk (Task 5). Leest alleen uit de
 * gecachte feed_items; praat zelf nooit met een externe bron.
 */
#[Layout('components.layouts.marketing', ['title' => 'Nieuws, Cloudmarktplaats'])]
class Reader extends Component
{
    public int $perPage = 40;

    public function boot(): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.news_reader'), 404);
    }

    /** @return Collection<int, FeedSource> */
    private function sources(): Collection
    {
        return FeedSource::query()->active()->get();
    }

    public function render(): View
    {
        $sources = $this->sources();

        $items = FeedItem::query()
            ->with('source')
            ->whereIn('feed_source_id', $sources->pluck('id'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->perPage)
            ->get();

        return view('livewire.news.reader', [
            'sources' => $sources,
            'items' => $items,
        ]);
    }
}
