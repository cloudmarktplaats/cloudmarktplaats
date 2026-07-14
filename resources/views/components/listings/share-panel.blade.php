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

        <div x-data="{ copied: false }" class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
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

            {{-- Hidden input doubles as the execCommand fallback target: the
                 Clipboard API is unavailable on plain http and older browsers,
                 and there it silently rejects. --}}
            <input
                type="text"
                x-ref="shareText"
                value="{{ $shareText }}"
                readonly
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
            >

            <button
                type="button"
                class="cmp-btn cmp-btn-ghost"
                @click="
                    const copy = navigator.clipboard
                        ? navigator.clipboard.writeText($refs.shareText.value)
                        : Promise.reject();
                    copy.catch(() => {
                        $refs.shareText.classList.remove('sr-only');
                        $refs.shareText.select();
                        document.execCommand('copy');
                        $refs.shareText.classList.add('sr-only');
                    }).finally(() => {
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    });
                "
            >
                <span x-show="!copied">{{ __('Kopieer tekst + link') }}</span>
                <span x-show="copied" x-cloak>{{ __('Gekopieerd') }}</span>
            </button>
        </div>
    </section>
@endcan
