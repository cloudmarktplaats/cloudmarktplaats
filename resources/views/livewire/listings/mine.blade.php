<div class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="cmp-section-label mb-3">{{ __('Beheer') }}</div>
            <h1 class="text-3xl font-bold tracking-display-tighter sm:text-4xl">
                {{ __('Mijn advertenties') }}
            </h1>
        </div>

        <a href="{{ route('listings.create') }}" class="cmp-btn cmp-btn-primary self-start">
            {{ __('Advertentie plaatsen') }}
        </a>
    </div>

    @if ($listings->isEmpty())
        {{-- Empty state — mirrors the Browse empty state, but framed for the
             owner: nothing of theirs to manage yet. --}}
        <div class="mt-12 rounded-sm border border-dashed border-cmp-border bg-cmp-surface px-6 py-16 text-center">
            <p class="font-display text-xl font-bold">{{ __('Je hebt nog geen advertenties.') }}</p>
            <p class="mt-2 text-sm text-cmp-muted">{{ __('Zodra je iets plaatst, verschijnt het hier — inclusief concepten die nog niet openbaar zijn.') }}</p>
            <a href="{{ route('listings.create') }}" class="cmp-btn cmp-btn-primary mt-6">
                {{ __('Plaats je eerste advertentie') }}
            </a>
        </div>
    @else
        <ul class="mt-10 flex flex-col gap-3">
            @foreach ($listings as $listing)
                @php
                    // House-style state labels + accent token, matching the
                    // owner-preview banner on the detail page.
                    $states = [
                        'draft'          => [__('Concept'), 'cmp-muted'],
                        'pending_review' => [__('In moderatie'), 'cmp-amber'],
                        'published'      => [__('Live'), 'cmp-blue'],
                        'sold'           => [__('Verkocht'), 'cmp-signal'],
                        'rejected'       => [__('Afgewezen'), 'cmp-amber'],
                        'archived'       => [__('Gearchiveerd'), 'cmp-muted'],
                    ];
                    [$stateLabel, $stateToken] = $states[$listing->state] ?? [ucfirst($listing->state), 'cmp-muted'];
                    $photo = $listing->photos->first();
                @endphp
                <li
                    wire:key="listing-{{ $listing->id }}"
                    class="flex flex-col gap-4 rounded-sm border border-cmp-border bg-cmp-surface p-4 sm:flex-row sm:items-center"
                >
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-sm bg-cmp-bg2">
                        @if ($photo)
                            <img src="{{ $photo->urlFor('card') }}" alt="{{ $listing->title }}" loading="lazy" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-cmp-faint" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="cmp-label-chip border-{{ $stateToken }} text-{{ $stateToken }}">{{ $stateLabel }}</span>
                            <h2 class="truncate font-medium text-cmp-text" title="{{ $listing->title }}">{{ $listing->title }}</h2>
                        </div>
                        <div class="mt-1 flex items-center gap-3 font-mono text-xs text-cmp-faint">
                            <span>€ {{ number_format($listing->price_cents / 100, 2, ',', '.') }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ ($listing->published_at ?? $listing->created_at)->format('Y-m-d') }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a href="/listings/{{ $listing->ulid }}-{{ $listing->slug }}" class="cmp-btn cmp-btn-ghost px-3 py-1.5 text-sm">
                            {{ __('Bekijken') }}
                        </a>
                        <a href="{{ route('listings.edit', $listing) }}" class="cmp-btn cmp-btn-secondary px-3 py-1.5 text-sm">
                            {{ __('Bewerken') }}
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
