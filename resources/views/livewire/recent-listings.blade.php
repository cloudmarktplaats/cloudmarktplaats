<section aria-labelledby="recent-heading">
    <div class="flex items-end justify-between mb-6">
        <div>
            <div class="cmp-section-label mb-3">{{ __('Recent aanbod') }}</div>
            <h2 id="recent-heading" class="text-2xl font-bold tracking-display-tight">{{ __('Net geplaatst') }}</h2>
        </div>
        <a href="{{ route('listings.index') }}" class="hidden sm:inline text-sm text-cmp-muted hover:text-cmp-ink">
            {{ __('Alle advertenties →') }}
        </a>
    </div>

    @if ($listings->isEmpty())
        <div class="rounded-sm border border-cmp-border bg-cmp-surface p-8 text-center">
            <p class="text-cmp-muted mb-4">{{ __('Nog geen advertenties. Wees de eerste.') }}</p>
            <a href="{{ route('register') }}" class="cmp-btn cmp-btn-primary">{{ __('Plaats er een') }}</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                @php
                    $photo = $listing->photos->first();
                    $href = '/listings/'.$listing->ulid.'-'.$listing->slug;
                @endphp
                <a href="{{ $href }}"
                   class="group block rounded-sm border border-cmp-border bg-cmp-surface overflow-hidden transition-colors duration-150 hover:border-cmp-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-cmp-signal">
                    <div class="relative h-40 bg-cmp-bg2 flex items-center justify-center overflow-hidden">
                        @if ($photo)
                            <img src="{{ $photo->urlFor('card') }}"
                                 alt="Foto van {{ $listing->title }}"
                                 loading="lazy"
                                 class="h-full w-full object-cover">
                        @else
                            <div class="absolute inset-0 cmp-grid-bg" aria-hidden="true"></div>
                            <span class="font-mono text-[10px] text-cmp-faint relative">geen foto</span>
                        @endif
                    </div>
                    <div class="p-3.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="truncate font-medium text-cmp-text" title="{{ $listing->title }}">{{ $listing->title }}</h3>
                            <span class="shrink-0 font-mono text-sm font-medium">
                                € {{ number_format($listing->price_cents / 100, 2, ',', '.') }}
                            </span>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between">
                            <span class="cmp-label-chip">{{ $listing->conditionLabel() }}</span>
                            <span class="font-mono text-[10px] text-cmp-faint">
                                {{ $listing->region_postcode ?: 'Nederland' }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6 text-center sm:hidden">
            <a href="{{ route('listings.index') }}" class="text-sm text-cmp-muted hover:text-cmp-ink">
                {{ __('Bekijk alle advertenties →') }}
            </a>
        </div>
    @endif
</section>
