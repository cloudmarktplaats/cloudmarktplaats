<x-layouts.marketing
    title="Cloudmarktplaats — geen Marktplaats, geen bol, wel kabels"
    description="Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin. Open source, privacy by design, geen cookiebanner-theater."
    :canonical="url('/')"
>

    {{-- ========== HERO ========== --}}
    <section class="relative overflow-hidden border-b border-cmp-border">
        <div class="absolute inset-0 cmp-grid-bg" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-cmp-bg pointer-events-none" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-5xl px-5 py-20 sm:px-8 sm:py-28 text-center">
            <div class="cmp-section-label justify-center mb-6">v0.1.0 · Foundation</div>

            <h1 class="font-display text-4xl font-bold tracking-display-tighter leading-[1.05] sm:text-6xl">
                Geen Marktplaats.<br>
                Geen bol.<br>
                <span class="text-cmp-blue">Wel kabels.</span>
            </h1>

            <p class="mx-auto mt-8 max-w-2xl text-cmp-muted text-base sm:text-lg leading-relaxed">
                Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin.
                Open source, privacy by design, geen cookiebanner-theater.
            </p>

            <div class="mt-10 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('register') }}" class="cmp-btn cmp-btn-primary justify-center">
                    Begin met verkopen
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="{{ route('listings.index') }}" class="cmp-btn cmp-btn-secondary justify-center">
                    Bekijk aanbod
                </a>
            </div>

            {{-- Social-proof pill strip --}}
            <div class="mt-14 flex flex-wrap gap-2 justify-center">
                @foreach ([
                    'Open source',
                    'AGPL-3.0',
                    'Privacy by design',
                    'Community-owned',
                ] as $pill)
                    <span class="inline-flex items-center font-mono text-[11px] tracking-wide text-cmp-muted bg-cmp-surface border border-cmp-border rounded-full px-3 py-1">
                        {{ $pill }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ========== FEATURES ========== --}}
    <section aria-labelledby="features-heading" class="mx-auto max-w-6xl px-5 sm:px-8 py-20">
        <div class="cmp-section-label mb-3">Waar het op rust</div>
        <h2 id="features-heading" class="text-3xl font-bold tracking-display-tight max-w-xl">
            Drie dingen die we niet als marketing roepen, maar in code zetten.
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-12">

            {{-- 1. Privacy --}}
            <div class="rounded-xl border border-cmp-border bg-cmp-surface p-6 transition-colors hover:border-cmp-blue/60">
                <div class="size-10 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mb-5">
                    {{-- Heroicons outline: shield-check --}}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 12l2 2 4-4"/>
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="font-mono text-[10px] text-cmp-blue mb-1 tracking-widest">01</div>
                <h3 class="text-lg font-bold tracking-display-tight">Privacy als architectuur</h3>
                <p class="text-sm text-cmp-muted mt-2 leading-relaxed">
                    IP-adressen gewist na 24 uur. EXIF gestript bij upload. Geen trackers, geen analytics.
                    Kijk maar in de code.
                </p>
            </div>

            {{-- 2. Open source --}}
            <div class="rounded-xl border border-cmp-border bg-cmp-surface p-6 transition-colors hover:border-cmp-blue/60">
                <div class="size-10 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mb-5">
                    {{-- Heroicons outline: code-bracket --}}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m17 8 4 4-4 4"/>
                        <path d="m7 8-4 4 4 4"/>
                        <path d="m14 4-4 16"/>
                    </svg>
                </div>
                <div class="font-mono text-[10px] text-cmp-blue mb-1 tracking-widest">02</div>
                <h3 class="text-lg font-bold tracking-display-tight">Open source</h3>
                <p class="text-sm text-cmp-muted mt-2 leading-relaxed">
                    AGPL-3.0. Forken mag, zelfhosten mag. Wijzigingen moeten publiek blijven.
                </p>
            </div>

            {{-- 3. Community-owned --}}
            <div class="rounded-xl border border-cmp-border bg-cmp-surface p-6 transition-colors hover:border-cmp-blue/60">
                <div class="size-10 rounded-md bg-cmp-blue/10 border border-cmp-blue/30 flex items-center justify-center mb-5">
                    {{-- Heroicons outline: users --}}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1A56FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="font-mono text-[10px] text-cmp-blue mb-1 tracking-widest">03</div>
                <h3 class="text-lg font-bold tracking-display-tight">Community-owned</h3>
                <p class="text-sm text-cmp-muted mt-2 leading-relaxed">
                    Geen aandeelhouders, geen exit-doel. Winst gaat naar hosting en de mensen die het draaiend houden.
                </p>
            </div>

        </div>
    </section>

    {{-- ========== TAGLINE ROTATOR ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 pb-16">
        <div
            x-data="{
                lines: [
                    'Jouw surplus. Iemands oplossing.',
                    'Marktplaats voor mensen die README\'s lezen.',
                    'Hardware ruilen, niet gevolgd worden.',
                    'Open source. Open marktplaats. Open boekhouding.',
                    'Tweedehands tech, eerstehands community.'
                ],
                index: 0,
                visible: true,
                init() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    setInterval(() => {
                        this.visible = false;
                        setTimeout(() => {
                            this.index = (this.index + 1) % this.lines.length;
                            this.visible = true;
                        }, 350);
                    }, 4000);
                }
            }"
            class="text-center font-mono text-sm text-cmp-muted h-6"
            aria-live="polite"
        >
            <span x-text="lines[index]"
                  x-show="visible"
                  x-transition:enter="transition-opacity duration-300"
                  x-transition:enter-start="opacity-0"
                  x-transition:enter-end="opacity-100"
                  x-transition:leave="transition-opacity duration-300"
                  x-transition:leave-start="opacity-100"
                  x-transition:leave-end="opacity-0">
                Jouw surplus. Iemands oplossing.
            </span>
        </div>
    </section>

    {{-- ========== RECENT LISTINGS ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <livewire:recent-listings :limit="6" />
    </section>

</x-layouts.marketing>
