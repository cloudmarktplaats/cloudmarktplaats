<div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">

    {{-- Header: framed as wandering, not searching. --}}
    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="cmp-section-label mb-3">
                @if ($categoryPath)
                    Categorie · {{ $categoryPath }}
                @else
                    De rommelmarkt
                @endif
            </div>
            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">
                Snuffel rond.
            </h1>
        </div>

        {{-- Sort toggle: recent vs. "Verras me". --}}
        <div class="flex items-center gap-1 self-start rounded-sm border border-cmp-border bg-cmp-surface p-1" role="group" aria-label="Sortering">
            <button
                type="button"
                wire:click="setSort('recent')"
                @class([
                    'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                    'bg-cmp-ink text-white' => $sort !== 'shuffle',
                    'text-cmp-muted hover:text-cmp-ink' => $sort === 'shuffle',
                ])
                aria-pressed="{{ $sort !== 'shuffle' ? 'true' : 'false' }}"
            >
                Recent
            </button>
            <button
                type="button"
                wire:click="setSort('shuffle')"
                @class([
                    'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                    'bg-cmp-ink text-white' => $sort === 'shuffle',
                    'text-cmp-muted hover:text-cmp-ink' => $sort !== 'shuffle',
                ])
                aria-pressed="{{ $sort === 'shuffle' ? 'true' : 'false' }}"
            >
                Verras me
            </button>
        </div>
    </div>

    {{-- Search stays available, but deliberately secondary to the grid. --}}
    <p class="mt-4 text-sm text-cmp-muted">
        Iets specifieks zoeken?
        <a href="{{ route('listings.search') }}" class="text-cmp-blue underline hover:text-cmp-blue-dark">Doorzoek het aanbod</a>.
    </p>

    @if ($listings->isEmpty())
        {{-- Empty state. --}}
        <div class="mt-12 rounded-sm border border-dashed border-cmp-border bg-cmp-surface px-6 py-16 text-center">
            <p class="font-display text-xl font-bold">Nog niks te snuffelen. Wees de eerste.</p>
            <p class="mt-2 text-sm text-cmp-muted">Er staat hier nog geen aanbod. Plaats jouw spullen en zet de markt op gang.</p>
            <a href="{{ route('listings.create') }}" class="cmp-btn cmp-btn-primary mt-6">
                Plaats een advertentie
            </a>
        </div>
    @else
        {{-- The grid is the star: 1 / 2 / 3-4 columns. --}}
        <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($listings as $listing)
                @php
                    $photo = $listing->photos->first();
                @endphp
                <a
                    href="/listings/{{ $listing->ulid }}-{{ $listing->slug }}"
                    wire:key="listing-{{ $listing->id }}"
                    class="group flex flex-col overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface transition-colors duration-150 hover:border-cmp-ink focus:outline-none focus-visible:border-cmp-ink focus-visible:ring-2 focus-visible:ring-cmp-signal"
                >
                    <div class="aspect-[4/3] overflow-hidden bg-cmp-bg2">
                        @if ($photo)
                            <img
                                src="{{ $photo->urlFor('card') }}"
                                alt="{{ $listing->title }}"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            >
                        @else
                            <div class="flex h-full w-full items-center justify-center text-cmp-faint" aria-hidden="true">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="truncate font-medium text-cmp-text" title="{{ $listing->title }}">
                                {{ $listing->title }}
                            </h2>
                            <span class="shrink-0 font-mono text-sm font-medium text-cmp-text">
                                € {{ number_format($listing->price_cents / 100, 2, ',', '.') }}
                            </span>
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-1">
                            <span class="cmp-label-chip">{{ $listing->conditionLabel() }}</span>
                            @if ($listing->region_postcode)
                                <span class="font-mono text-[10px] text-cmp-faint">{{ $listing->region_postcode }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($hasMore)
            {{-- Infinite scroll: Alpine IntersectionObserver auto-loads as the
                 sentinel scrolls into view. The button is the visible fallback
                 (and the only control when JS auto-loading is unavailable). --}}
            <div
                x-data
                x-intersect.margin.400px="$wire.loadMore()"
                class="mt-10 flex justify-center"
            >
                <button
                    type="button"
                    wire:click="loadMore"
                    wire:loading.attr="disabled"
                    class="cmp-btn cmp-btn-secondary"
                >
                    <span wire:loading.remove wire:target="loadMore">Meer laden</span>
                    <span wire:loading wire:target="loadMore">Laden…</span>
                </button>
            </div>
        @endif
    @endif
</div>
