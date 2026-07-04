<x-layouts.marketing
    title="Doneren — Cloudmarktplaats"
    description="Cloudmarktplaats draait op donaties. Geen advertenties, geen datahandel, geen commissie — de servers en de mensen kosten geld."
    :canonical="url('/doneren')"
>
    <section class="mx-auto max-w-2xl px-5 py-16 sm:px-8 sm:py-20">

        <div class="cmp-section-label mb-4">Doneren</div>
        <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
            Dit draait op<br><span class="text-cmp-signal">donaties.</span>
        </h1>

        <p class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75]">
            Geen advertenties, geen datahandel, geen commissie op je verkopen. Cloudmarktplaats
            verdient niets aan jou — maar de servers en de mensen die het draaiend houden kosten
            wél geld. Een donatie houdt het onafhankelijk. Alles wat binnenkomt gaat eerst naar
            hosting, daarna naar wie er meer dan een avondje per week aan meewerkt.
        </p>

        {{-- Primaire route: gehoste betaalpagina (iDEAL/kaart via Revolut). --}}
        <div class="mt-10 flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <a href="https://checkout.revolut.com/pay/53761058-e0f5-4e76-a75a-e80c6b4fa5ca"
               class="cmp-btn cmp-btn-primary" rel="noopener external">
                Doneer nu
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <span class="font-mono text-[11px] text-cmp-faint">iDEAL of kaart · via Revolut · kies zelf het bedrag</span>
        </div>

        {{-- Eenmalig via bankoverschrijving (SEPA). --}}
        <div class="mt-10 rounded-sm border-2 border-cmp-ink bg-cmp-surface p-6">
            <div class="font-mono text-[11px] uppercase tracking-[0.14em] text-cmp-muted mb-4">
                Eenmalig · bankoverschrijving
            </div>
            <dl class="space-y-2 text-sm">
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">Begunstigde</dt>
                    <dd class="font-medium text-right">Aldewereld Consultancy</dd>
                </div>
                <div class="flex items-center justify-between gap-4" x-data="{ copied: false }">
                    <dt class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">IBAN</dt>
                    <dd class="flex items-center gap-3">
                        <span class="font-mono font-medium">NL19&nbsp;REVO&nbsp;7336&nbsp;3721&nbsp;47</span>
                        <button type="button"
                                @click="navigator.clipboard.writeText('NL19REVO7336372147'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="rounded-sm border border-cmp-border px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-cmp-muted hover:border-cmp-ink hover:text-cmp-ink transition-colors">
                            <span x-text="copied ? 'gekopieerd' : 'kopieer'">kopieer</span>
                        </button>
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">BIC</dt>
                    <dd class="font-mono font-medium text-right">REVONL22</dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="font-mono text-[11px] uppercase tracking-wide text-cmp-muted">Valuta</dt>
                    <dd class="font-mono font-medium text-right">EUR</dd>
                </div>
            </dl>
        </div>

        {{-- Maandelijks via GitHub Sponsors. --}}
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-sm border border-cmp-border bg-cmp-surface p-6">
            <div>
                <div class="font-mono text-[11px] uppercase tracking-[0.14em] text-cmp-muted mb-1">Maandelijks</div>
                <p class="text-sm text-cmp-muted">Liever een vast bedrag per maand? Dat kan via GitHub Sponsors.</p>
            </div>
            <a href="https://github.com/sponsors/NickAldewereld" class="cmp-btn cmp-btn-secondary" rel="noopener external">
                ♥ GitHub Sponsors
            </a>
        </div>

        {{-- Eerlijk over de status: geen ANBI, geen aftrek, geen tegenprestatie. --}}
        <div class="mt-10 border-t border-cmp-border pt-6 font-mono text-[11px] leading-relaxed text-cmp-faint space-y-2">
            <p>Een donatie is een gift, geen aankoop: er staat geen tegenprestatie tegenover en er is geen herroepingsrecht.</p>
            <p>Aldewereld Consultancy is geen goededoelenorganisatie (geen ANBI-status). Donaties zijn daardoor <span class="text-cmp-muted">niet fiscaal aftrekbaar</span>.</p>
            <p>Ontvanger: Aldewereld Consultancy, KvK 61862533, Nieuwe Hemweg 26, 1013 CX Amsterdam (postadres). Vragen: <a href="mailto:info@cloudmarktplaats.nl" class="underline hover:text-cmp-muted">info@cloudmarktplaats.nl</a>.</p>
        </div>

    </section>
</x-layouts.marketing>
