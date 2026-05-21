@php
    $values = [
        ['Privacy is een ontwerpkeuze, geen marketing.', 'Data die we niet hebben kan niet lekken, niet verkocht worden en niet opgevraagd worden. We bewaren het minimale.'],
        ['Open source, AGPL.', 'De code is publiek, de wijzigingen ook. Als je het beter kan, fork hem.'],
        ['De community bezit het platform.', 'Geen aandeelhouders, geen exit, geen overname.'],
        ['Eerlijk verdienmodel.', 'Donaties, sponsoring, optionele premium listings. Geen verborgen kosten, geen datavers, geen affiliate links.'],
        ['Geen algoritmische manipulatie.', 'Sorteren op datum, op prijs, op afstand. Punt. Geen "voor jou aanbevolen" dat eigenlijk "voor onze CPM aanbevolen" is.'],
        ['Tweedehands eerst.', 'Het platform bestaat omdat hardware langer mee moet gaan. Doorverhuizen is duurzamer dan recyclen.'],
        ['Geen discriminatie.', 'Niet in moderatie, niet in functionaliteit, niet in wie hier mag handelen. Wettelijk en menselijk standaard.'],
        ['Geen illegale handel.', 'Geen wapens, geen gestolen waar, geen counterfeit, geen drugs. We modereren reactief op meldingen en proactief waar het kan.'],
        ['Transparante moderatie.', 'Beslissingen zijn appellabel. Modlogs zijn intern beschikbaar en op verzoek inzichtelijk voor betrokkenen.'],
        ['Federatie waar het kan.', 'Mastodon, Matrix, RSS — communicatie hoort niet op één plek opgesloten te zitten.'],
        ['Vrijwilligers worden betaald als er geld is.', 'Sponsoring en donaties gaan eerst naar hosting, daarna naar de mensen die meer dan een avondje per week meedraaien.'],
        ['Geen activisme-performance.', 'We schreeuwen niet over privacy in headers, we bouwen het. Als je code wil zien, hier is hij.'],
    ];
@endphp

<x-layouts.marketing
    title="Waarden — Cloudmarktplaats"
    description="Geen mission statement. Wat we wel en niet doen, in twaalf punten."
    :canonical="url('/waarden')"
>

    <section class="mx-auto max-w-6xl px-5 sm:px-8 py-16 sm:py-20">

        <header class="max-w-2xl mb-14">
            <div class="cmp-section-label mb-4">Waarden</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                Geen mission statement.<br>
                <span class="text-cmp-muted">Wat we wel en niet doen.</span>
            </h1>
        </header>

        <ol class="grid grid-cols-1 md:grid-cols-2 gap-4" role="list">
            @foreach ($values as $i => [$title, $body])
                <li class="group rounded-xl border border-cmp-border bg-cmp-surface p-6 transition-colors duration-150 hover:border-cmp-blue/70">
                    <div class="font-mono text-[11px] text-cmp-blue tracking-widest mb-3">
                        {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <h2 class="text-base font-bold tracking-display-tight">{{ $title }}</h2>
                    <p class="text-sm text-cmp-muted mt-2 leading-relaxed">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('about') }}" class="hover:text-cmp-blue">→ Over ons</a>
            <a href="{{ route('faq') }}" class="hover:text-cmp-blue">→ Veelgestelde vragen</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-blue" rel="noopener external">→ Code op GitHub</a>
        </div>

    </section>

</x-layouts.marketing>
