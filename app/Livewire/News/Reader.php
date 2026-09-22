<?php

declare(strict_types=1);

namespace App\Livewire\News;

use App\Models\FeedItem;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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

    /**
     * De effectief getoonde bron-ids. Geen account of geen keuze gemaakt:
     * alle actieve bronnen. Wel een keuze: precies die rijen.
     *
     * @param  Collection<int, FeedSource>  $active
     * @return list<int>
     */
    private function selectedSourceIds(Collection $active): array
    {
        $activeIds = $this->toIntList($active->pluck('id')->all());
        $user = auth()->user();

        if ($user === null) {
            return $activeIds;
        }

        $chosen = $this->toIntList($user->feedSources()->pluck('feed_sources.id')->all());
        $chosenActive = array_values(array_intersect($chosen, $activeIds));

        // Geen keuze, of elke gekozen bron is intussen gedeactiveerd: toon alles.
        return $chosenActive === [] ? $activeIds : $chosenActive;
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<int>
     */
    private function toIntList(array $ids): array
    {
        return array_values(array_map(intval(...), $ids));
    }

    /**
     * Zet een bron aan of uit voor het ingelogde lid. Bij de eerste toggle
     * bestaat er nog geen rij ("geen keuze" = alles); we materialiseren dan
     * eerst de huidige effectieve selectie, zodat uitvinken van 1 bron niet
     * per ongeluk "toon niets" of "toon alles" betekent.
     */
    public function toggleSource(int $sourceId): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        if ($user->feedSources()->count() === 0) {
            $user->feedSources()->sync($this->sources()->pluck('id')->all());
        }

        // Het laatste vinkje mag niet uit: een lege pivot betekent "geen keuze
        // = alles", dus dat zou terugklappen naar alle bronnen. Minstens 1 blijft.
        $selected = $this->toIntList($user->feedSources()->pluck('feed_sources.id')->all());
        if ($selected === [$sourceId]) {
            return;
        }

        $user->feedSources()->toggle([$sourceId]);
    }

    public function markRead(int $sourceId): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        DB::table('user_feed_reads')->updateOrInsert(
            ['user_id' => $user->id, 'feed_source_id' => $sourceId],
            ['read_at' => now()],
        );
    }

    public function markAllRead(): void
    {
        foreach ($this->selectedSourceIds($this->sources()) as $id) {
            $this->markRead($id);
        }
    }

    /**
     * Ongelezen per bron: items nieuwer dan het watermerk. Geen watermerk =
     * alles ongelezen. Alleen voor een ingelogd lid; anoniem is alles leeg.
     *
     * @param  list<int>  $sourceIds
     * @return array<int, int>
     */
    private function unreadCounts(array $sourceIds): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $watermarks = DB::table('user_feed_reads')
            ->where('user_id', $user->id)
            ->pluck('read_at', 'feed_source_id');

        $counts = [];

        foreach ($sourceIds as $id) {
            $counts[$id] = FeedItem::query()
                ->where('feed_source_id', $id)
                ->when($watermarks[$id] ?? null, fn ($q, $at) => $q->where('published_at', '>', $at))
                ->count();
        }

        return $counts;
    }

    public function render(): View
    {
        $sources = $this->sources();
        $sourceIds = $this->selectedSourceIds($sources);

        $items = FeedItem::query()
            ->with('source')
            ->whereIn('feed_source_id', $sourceIds)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->perPage)
            ->get();

        return view('livewire.news.reader', [
            'sources' => $sources,
            'selectedSourceIds' => $sourceIds,
            'unreadCounts' => $this->unreadCounts($sourceIds),
            'items' => $items,
        ]);
    }
}
