<x-layouts.marketing
    :title="__('404 — Niet gevonden · Cloudmarktplaats')"
    description="De pagina die je zocht bestaat hier niet (meer)."
>

    <section class="relative mx-auto max-w-2xl px-5 sm:px-8 py-24 sm:py-32">

        {{-- Easter egg: a faint glyph-rain drifts behind the 404 — a small
             "lost in the machine" nod. JS attaches a canvas here; it stays
             subtle and switches off under prefers-reduced-motion. --}}
        <div data-matrix-rain aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"></div>

        <p class="relative font-mono text-cmp-muted text-[120px] sm:text-[180px] leading-none tracking-tight">404</p>

        <h1 class="mt-4 text-3xl sm:text-4xl font-bold tracking-display-tighter">{{ __('Niet gevonden') }}</h1>

        <p class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75]">
            {{ __("De URL die je probeerde te bereiken bestaat hier niet (meer). Mogelijke oorzaken, in volgorde van waarschijnlijkheid:") }}
        </p>

        <ol class="mt-6 space-y-3" role="list">
            @foreach ([
                __('De advertentie is verlopen of verwijderd door de verkoper.'),
                __('Je volgde een oude link uit een mail of Slack-bericht.'),
                __('Je hebt iets in de URL met de hand getypt en dat ging mis.'),
                __('Wij hebben iets stuk gemaakt. Onwaarschijnlijk maar niet onmogelijk.'),
            ] as $i => $cause)
                <li class="flex items-start gap-4">
                    <span class="font-mono text-[11px] text-cmp-blue tracking-widest mt-1.5 shrink-0">
                        {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <span class="text-cmp-text/90">{{ $cause }}</span>
                </li>
            @endforeach
        </ol>

        <div class="mt-12 pt-6 border-t border-cmp-border flex flex-wrap gap-3">
            <a href="{{ url('/') }}" class="cmp-btn cmp-btn-primary">{{ __('Naar de homepage') }}</a>
            <a href="{{ route('listings.index') }}" class="cmp-btn cmp-btn-secondary">{{ __('Doorzoek het aanbod') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="cmp-btn cmp-btn-ghost" rel="noopener external">{{ __('Open een GitHub-issue') }}</a>
        </div>

    </section>

</x-layouts.marketing>
