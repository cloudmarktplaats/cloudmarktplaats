<x-layouts.marketing
    :title="'500 — ' . __('Iets aan onze kant') . ' · Cloudmarktplaats'"
    :description="__('Server gaf een fout terug. Ligt aan ons, niet aan jou.')"
>

    <section class="mx-auto max-w-2xl px-5 sm:px-8 py-24 sm:py-32">

        <p class="font-mono text-cmp-muted text-[120px] sm:text-[180px] leading-none tracking-tight">500</p>

        <h1 class="mt-4 text-3xl sm:text-4xl font-bold tracking-display-tighter">{{ __('Iets aan onze kant') }}</h1>

        <div class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75] space-y-4">
            <p>{{ __('De server gaf een fout terug. Dat ligt aan ons, niet aan jou.') }}</p>
            <p>
                {{ __('We hebben de fout automatisch gelogd (zonder je IP-adres — die bewaren we sowieso maximaal 24 uur). Als je het binnen een paar minuten opnieuw probeert, is er een goede kans dat het werkt.') }}
            </p>
            <p>
                {{ __('Werkt het daarna nog niet? Open een issue op') }}
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues"
                   class="text-cmp-blue hover:text-cmp-blue-light underline underline-offset-4" rel="noopener external">GitHub</a>
                {{ __('met wat je deed toen het misging. Hoe specifieker, hoe sneller iemand het oplost.') }}
            </p>
        </div>

        <div class="mt-12 pt-6 border-t border-cmp-border flex flex-wrap gap-3">
            <button type="button"
                    x-data
                    x-on:click="history.back()"
                    class="cmp-btn cmp-btn-primary">
                {{ __('Probeer opnieuw') }}
            </button>
            <a href="{{ url('/') }}" class="cmp-btn cmp-btn-secondary">{{ __('Naar de homepage') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="cmp-btn cmp-btn-ghost" rel="noopener external">GitHub Issues</a>
        </div>

    </section>

</x-layouts.marketing>
