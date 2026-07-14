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

        <div
            x-data="{
                copied: false,
                failed: false,
                async copy() {
                    const input = $refs.shareText;

                    try {
                        // Clipboard API needs a secure context. Production is
                        // https and localhost counts as secure, so this is the
                        // normal path; the catch is the honest escape hatch.
                        if (! navigator.clipboard) {
                            throw new Error('clipboard api unavailable');
                        }
                        await navigator.clipboard.writeText(input.value);
                    } catch (error) {
                        // Never claim success we can't verify: reveal the text
                        // and let the visitor copy it themselves.
                        this.failed = true;
                        input.classList.remove('sr-only');
                        input.removeAttribute('aria-hidden');
                        input.removeAttribute('tabindex');
                        input.select();

                        return;
                    }

                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                },
            }"
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
        >
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

            <button type="button" class="cmp-btn cmp-btn-ghost" @click="copy()">
                <span x-show="!copied">{{ __('Kopieer tekst + link') }}</span>
                <span x-show="copied" x-cloak>{{ __('Gekopieerd') }}</span>
            </button>

            {{-- Starts hidden and is revealed only when the clipboard write
                 fails, so the visitor always has a way to get the text. --}}
            <input
                type="text"
                x-ref="shareText"
                value="{{ $shareText }}"
                readonly
                class="sr-only w-full font-mono text-xs sm:w-auto sm:flex-1"
                tabindex="-1"
                aria-hidden="true"
            >

            <p x-show="failed" x-cloak class="text-sm text-cmp-muted">
                {{ __('Kopiëren lukte niet — selecteer de tekst hierboven en kopieer zelf.') }}
            </p>
        </div>
    </section>
@endcan
