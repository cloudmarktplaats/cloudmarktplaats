<x-layouts.marketing
    title="404 — Niet gevonden · Cloudmarktplaats"
    description="De pagina die je zocht bestaat hier niet (meer)."
>

    <section class="mx-auto max-w-2xl px-5 sm:px-8 py-24 sm:py-32">

        <p class="font-mono text-cmp-muted text-[120px] sm:text-[180px] leading-none tracking-tight">404</p>

        <h1 class="mt-4 text-3xl sm:text-4xl font-bold tracking-display-tighter">Niet gevonden</h1>

        <p class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75]">
            De URL die je probeerde te bereiken bestaat hier niet (meer). Mogelijke oorzaken,
            in volgorde van waarschijnlijkheid:
        </p>

        <ol class="mt-6 space-y-3" role="list">
            @foreach ([
                'De advertentie is verlopen of verwijderd door de verkoper.',
                'Je volgde een oude link uit een mail of Slack-bericht.',
                'Je hebt iets in de URL met de hand getypt en dat ging mis.',
                'Wij hebben iets stuk gemaakt. Onwaarschijnlijk maar niet onmogelijk.',
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
            <a href="{{ url('/') }}" class="cmp-btn cmp-btn-primary">Naar de homepage</a>
            <a href="{{ route('listings.index') }}" class="cmp-btn cmp-btn-secondary">Doorzoek het aanbod</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="cmp-btn cmp-btn-ghost" rel="noopener external">Open een GitHub-issue</a>
        </div>

    </section>

</x-layouts.marketing>
