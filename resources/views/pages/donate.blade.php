<x-layouts.marketing
    :title="__('Doneren — Cloudmarktplaats')"
    :description="__('Cloudmarktplaats draait op donaties. Geen advertenties, geen datahandel, geen commissie — de servers en de mensen kosten geld.')"
    :canonical="url('/doneren')"
>
    <section class="mx-auto max-w-2xl px-5 py-16 sm:px-8 sm:py-20">

        <div class="cmp-section-label mb-4">{{ __('Doneren') }}</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
            {{ __('Dit draait op') }}<br><span class="text-cmp-signal">{{ __('donaties.') }}</span>
        </h1>

        <p class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75]">
            {{ __('Geen advertenties, geen datahandel, geen commissie op je verkopen. Cloudmarktplaats verdient niets aan jou — maar de servers en de mensen die het draaiend houden kosten wél geld. Een donatie houdt het onafhankelijk. Alles wat binnenkomt gaat eerst naar hosting, daarna naar wie er meer dan een avondje per week aan meewerkt.') }}
        </p>

        {{-- Primaire route: gehoste betaalpagina (iDEAL/kaart via Revolut). --}}
        <div class="mt-10 flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <a href="https://checkout.revolut.com/pay/53761058-e0f5-4e76-a75a-e80c6b4fa5ca"
               class="cmp-btn cmp-btn-primary" rel="noopener external">
                {{ __('Doneer nu') }}
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <span class="font-mono text-[11px] text-cmp-faint">{{ __('iDEAL of kaart · via Revolut · kies zelf het bedrag') }}</span>
        </div>

        {{-- Maandelijks via GitHub Sponsors. --}}
        <div class="mt-10 flex flex-wrap items-center justify-between gap-3 rounded-sm border border-cmp-border bg-cmp-surface p-6">
            <div>
                <div class="font-mono text-[11px] uppercase tracking-[0.14em] text-cmp-muted mb-1">{{ __('Maandelijks') }}</div>
                <p class="text-sm text-cmp-muted">{{ __('Liever een vast bedrag per maand? Dat kan via GitHub Sponsors.') }}</p>
            </div>
            <a href="https://github.com/sponsors/NickAldewereld" class="cmp-btn cmp-btn-secondary" rel="noopener external">
                ♥ GitHub Sponsors
            </a>
        </div>

        {{-- Eerlijk over de status: geen ANBI, geen aftrek, geen tegenprestatie. --}}
        <div class="mt-10 border-t border-cmp-border pt-6 font-mono text-[11px] leading-relaxed text-cmp-faint space-y-2">
            <p>{{ __('Een donatie is een gift, geen aankoop: er staat geen tegenprestatie tegenover en er is geen herroepingsrecht.') }}</p>
            {{-- Rekeningnummers horen niet publiek op een pagina die iedereen kan
                 scrapen; wie per se wil overmaken vraagt de gegevens gewoon op. --}}
            <p>{!! __('Liever een gewone bankoverschrijving? Vraag de gegevens op via <a href="mailto:info@cloudmarktplaats.nl" class="underline hover:text-cmp-muted">info@cloudmarktplaats.nl</a>.') !!}</p>
            {{-- Het oude IBAN heeft publiek gestaan en is dus gescrapet. Deze regel
                 kost niets en haalt de bodem onder een nagemaakte oproep vandaan. --}}
            <p>{{ __('Wij vragen je nooit uit onszelf om een overboeking — niet per e-mail, niet via een bericht op het platform. Krijg je zoiets namens Cloudmarktplaats, dan is het niet van ons.') }}</p>
            <p>{!! __('Aldewereld Consultancy is geen goededoelenorganisatie (geen ANBI-status). Donaties zijn daardoor <span class="text-cmp-muted">niet fiscaal aftrekbaar</span>.') !!}</p>
            <p>{{ __('Ontvanger:') }} Aldewereld Consultancy, KvK 61862533, Nieuwe Hemweg 26, 1013 CX Amsterdam (postadres). {{ __('Vragen:') }} <a href="mailto:info@cloudmarktplaats.nl" class="underline hover:text-cmp-muted">info@cloudmarktplaats.nl</a>.</p>
        </div>

    </section>
</x-layouts.marketing>
