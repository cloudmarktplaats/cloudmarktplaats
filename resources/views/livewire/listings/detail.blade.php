<div class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">
    <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1 text-sm text-cmp-muted hover:text-cmp-ink">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Terug naar het aanbod
    </a>

    <article class="mt-6 overflow-hidden rounded-sm border border-cmp-border bg-cmp-surface">
        @if ($listing->photos->isNotEmpty())
            <div class="grid grid-cols-1 gap-1 bg-cmp-bg2 sm:grid-cols-3">
                @foreach ($listing->photos as $photo)
                    <img
                        src="{{ $photo->urlFor('card') }}"
                        alt="{{ $listing->title }}"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover"
                    >
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-8 p-6 sm:p-8 md:grid-cols-[1fr,260px]">
            <div>
                <h1 class="text-2xl font-bold tracking-display-tight sm:text-3xl">{{ $listing->title }}</h1>

                <div class="mt-3 font-mono text-2xl font-medium text-cmp-text">
                    € {{ number_format($listing->price_cents / 100, 2, ',', '.') }}
                </div>

                <p class="mt-2 text-sm text-cmp-muted">
                    Verkoper: {{ $listing->user->display_name ?? 'onbekend' }}
                    @if ($listing->region_postcode)
                        · {{ $listing->region_postcode }}
                    @endif
                </p>

                <div class="prose mt-6 max-w-none text-cmp-text">
                    {!! nl2br(e($listing->description)) !!}
                </div>

                {{-- Contact relay: the "Stuur bericht" button toggles the form in
                     place via Alpine — no page reload, no account required. --}}
                <div x-data="{ open: false }" class="mt-8 border-t border-cmp-border pt-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <button
                            type="button"
                            x-on:click="open = !open"
                            x-bind:aria-expanded="open.toString()"
                            aria-controls="contact-seller-panel"
                            class="cmp-btn cmp-btn-primary"
                        >
                            Stuur bericht
                        </button>
                        <a href="/reports/listing/{{ $listing->id }}" class="text-sm text-cmp-muted underline hover:text-cmp-amber">
                            Rapporteer deze advertentie
                        </a>
                    </div>

                    <div id="contact-seller-panel" x-show="open" x-cloak x-collapse class="mt-5">
                        <livewire:contact-seller :listing="$listing" :key="'contact-'.$listing->id" />
                    </div>
                </div>
            </div>

            {{-- De inventarissticker: alle feitelijke kenmerken van deze
                 advertentie bij elkaar, in thermal-label-stijl (DESIGN.md). --}}
            <x-inventory-label
                class="h-fit md:justify-self-end w-full md:w-[260px]"
                :rows="array_filter([
                    'Conditie'  => $listing->conditionLabel(),
                    'Regio'     => $listing->region_postcode ?: null,
                    'Geplaatst' => $listing->published_at?->format('Y-m-d') ?? $listing->created_at->format('Y-m-d'),
                    'Ref'       => strtoupper(substr($listing->ulid, -8)),
                ])"
                highlight="Conditie"
            />
        </div>
    </article>
</div>
