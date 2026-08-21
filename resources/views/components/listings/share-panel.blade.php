@props(['listing'])

{{-- Owner-only and published-only, both encoded in the policy. A guest fails
     the ability automatically: share() type-hints a non-nullable User. --}}
@can('share', $listing)
    @php
        $share = app(App\Support\ShareLinkBuilder::class);
        $shareText = $share->shareText($listing);
    @endphp

    <section class="mt-6 rounded-sm border border-cmp-border bg-cmp-surface p-5 sm:p-6">
        <div class="cmp-section-label mb-3">{{ __('Delen') }}</div>
        <h2 class="font-display text-xl font-bold tracking-display-tight">
            {{ __('Deel je advertentie') }}
        </h2>
        <p class="mt-2 text-sm text-cmp-muted">
            {{ __('Je advertentie staat live. Delen levert meestal de eerste reacties op.') }}
        </p>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <a
                href="{{ $share->linkedIn($listing) }}"
                target="_blank"
                rel="noopener external"
                class="cmp-btn cmp-btn-primary"
            >{{ __('Deel op LinkedIn') }}</a>

            <a
                href="{{ $share->mainDeckUrl() }}"
                target="_blank"
                rel="noopener external"
                class="cmp-btn cmp-btn-secondary"
            >{{ __('Deel op MainDeck') }}</a>

            <x-copy-button :text="$shareText" :label="__('Kopieer tekst + link')" />
        </div>
    </section>
@endcan
