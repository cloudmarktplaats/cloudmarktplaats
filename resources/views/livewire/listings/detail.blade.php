<div class="mx-auto max-w-3xl px-5 py-10 sm:px-8 sm:py-14">
    @php
        $badge = match ($listing->condition) {
            'new'       => 'border-cmp-blue/40 text-cmp-blue',
            'used'      => 'border-cmp-signal/40 text-cmp-signal',
            'defective' => 'border-cmp-amber/40 text-cmp-amber',
            default     => 'border-cmp-muted/40 text-cmp-muted',
        };
    @endphp

    <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1 text-sm text-cmp-muted hover:text-cmp-text">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Terug naar het aanbod
    </a>

    <article class="mt-6 overflow-hidden rounded-xl border border-cmp-border bg-cmp-surface">
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

        <div class="p-6 sm:p-8">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-wide {{ $badge }}">
                    {{ $listing->conditionLabel() }}
                </span>
                <span class="font-mono text-lg font-medium text-cmp-text">
                    € {{ number_format($listing->price_cents / 100, 2, ',', '.') }}
                </span>
            </div>

            <h1 class="mt-4 text-2xl font-bold tracking-display-tight sm:text-3xl">{{ $listing->title }}</h1>

            <p class="mt-2 text-sm text-cmp-muted">
                Verkoper: {{ $listing->user->display_name ?? 'onbekend' }}
                @if ($listing->region_postcode)
                    · {{ $listing->region_postcode }}
                @endif
            </p>

            <div class="prose prose-invert mt-6 max-w-none text-cmp-text">
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
    </article>
</div>
