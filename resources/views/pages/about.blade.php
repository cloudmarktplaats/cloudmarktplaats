<x-layouts.marketing
    title="Over Cloudmarktplaats — waarom dit bestaat"
    description="Waarom we een open source, community-owned marktplaats voor IT-hardware bouwen — geen trackers, geen exit-doel."
    :canonical="url('/over-ons')"
>

    <article class="mx-auto max-w-[680px] px-5 sm:px-8 py-16 sm:py-20">

        <header class="mb-12">
            <div class="cmp-section-label mb-4">Over ons</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">Waarom dit bestaat</h1>
        </header>

        <div class="space-y-5 text-cmp-text/90 text-[15px] leading-[1.75]">
            <p>
                Marktplaats was ooit een prikbord en is nu een advertentienetwerk dat je profileert en
                doorlinkt. Vinted rapporteert alles boven de DAC7-drempel automatisch aan de Belastingdienst,
                ongeacht of je een professionele verkoper bent of gewoon je oude koptelefoon kwijt wil.
                Tweakers Vraag &amp; Aanbod is een forumdraad met een kassa — fijn, maar geen volwaardige
                marktplaats. En de echte alternatieven zijn webshops die je niet wíl steunen: bol, Coolblue,
                Aliexpress, Amazon.
            </p>
            <p>
                Voor wie tweedehands tech zoekt — een herstelde server, een dev board uit een opgeheven
                project, surplus van een ontmantelde meterkast — is dat tussenliggende terrein dunbevolkt.
            </p>
            <p>
                Cloudmarktplaats is een poging om dat gat te vullen met iets wat de gebruikers zelf bezitten.
            </p>
        </div>

        {{-- ==== Wat we anders doen ==== --}}
        <section class="mt-16">
            <div class="cmp-section-label mb-4">Anders</div>
            <h2 class="text-2xl font-bold tracking-display-tight">Wat we anders doen</h2>

            <ul class="mt-8 space-y-5">
                <li class="flex gap-4 items-start">
                    <div class="size-10 shrink-0 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mt-0.5">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12l2 2 4-4"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold">Privacy als architectuur, niet als belofte</h3>
                        <p class="text-cmp-muted text-sm leading-relaxed mt-1">
                            IP-adressen worden binnen 24 uur uit ons systeem gestript
                            (<code class="font-mono text-cmp-text text-[12px]">IpStripperJob</code>, zie de code).
                            EXIF en GPS worden uit elke uploadfoto verwijderd voordat hij online staat.
                            Geen third-party trackers, geen Google Analytics, geen Facebook Pixel.
                        </p>
                    </div>
                </li>
                <li class="flex gap-4 items-start">
                    <div class="size-10 shrink-0 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mt-0.5">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m17 8 4 4-4 4"/><path d="m7 8-4 4 4 4"/><path d="m14 4-4 16"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold">Open source</h3>
                        <p class="text-cmp-muted text-sm leading-relaxed mt-1">
                            De volledige codebase staat onder AGPL-3.0 op GitHub. Forken mag, zelfhosten mag —
                            als je een aangepaste versie publiek draait, moet je je wijzigingen ook publiek
                            maken.
                        </p>
                    </div>
                </li>
                <li class="flex gap-4 items-start">
                    <div class="size-10 shrink-0 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mt-0.5">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold">Community-owned</h3>
                        <p class="text-cmp-muted text-sm leading-relaxed mt-1">
                            Het platform wordt door vrijwilligers gebouwd, gemodereerd en gevoed. Winst —
                            voor zover die er ooit komt — gaat terug naar hosting, infrastructuur en de
                            mensen die het beheren. Geen aandeelhouders, geen exit-doel.
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        {{-- ==== Hoe het wordt betaald ==== --}}
        <section class="mt-16">
            <div class="cmp-section-label mb-4">Geld</div>
            <h2 class="text-2xl font-bold tracking-display-tight">Hoe het wordt betaald</h2>
            <p class="text-cmp-muted text-sm mt-3">Geen verborgen verdienmodel:</p>

            <div class="mt-6 space-y-3">
                @foreach ([
                    ['label' => '01', 'name' => 'Donaties', 'body' => 'Eenmalig of maandelijks, via een open boekhouding.'],
                    ['label' => '02', 'name' => 'Sponsoring', 'body' => 'Bedrijven en zelfstandigen die de community willen steunen, krijgen een vermelding op een sponsorpagina. Geen native advertenties. Geen invloed op moderatie.'],
                    ['label' => '03', 'name' => 'Premium listings (later)', 'body' => 'Optioneel je advertentie boven aan de pagina. Niet om onze rekening te vullen — om de rekening van de hoster te betalen.'],
                ] as $item)
                    <div class="flex items-start gap-4 rounded-xl border border-cmp-border bg-cmp-surface p-5">
                        <span class="font-mono text-[11px] text-cmp-blue mt-1 tracking-widest shrink-0">{{ $item['label'] }}</span>
                        <div>
                            <h3 class="text-base font-semibold">{{ $item['name'] }}</h3>
                            <p class="text-sm text-cmp-muted mt-1 leading-relaxed">{{ $item['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-cmp-muted text-sm mt-6 leading-relaxed">
                We publiceren onze kosten en inkomsten zodra er meer dan een bonnetje per maand binnenkomt.
            </p>
        </section>

        {{-- ==== Tot slot ==== --}}
        <section class="mt-16">
            <div class="cmp-section-label mb-4">Tot slot</div>
            <p class="text-[15px] leading-[1.75] text-cmp-text/90">
                Cloudmarktplaats is geen startup, geen scale-up, geen unicorn in wording. Het is een door
                de tech-community gebouwd alternatief voor mensen die hardware willen verhandelen zonder dat
                hun gegevens onderdeel worden van het product. Doe mee als je er iets in herkent. Verkoop
                iets. Koop iets. Of fork de boel en draai je eigen versie.
            </p>

            <div class="mt-10 pt-6 border-t border-cmp-border flex flex-wrap gap-x-6 gap-y-2 font-mono text-[11px] text-cmp-muted">
                <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-blue" rel="noopener external">→ Code op GitHub</a>
                <a href="{{ route('faq') }}" class="hover:text-cmp-blue">→ Veelgestelde vragen</a>
                <a href="{{ route('values') }}" class="hover:text-cmp-blue">→ Onze waarden</a>
            </div>
        </section>

    </article>

</x-layouts.marketing>
