<x-layouts.marketing
    title="Cloudmarktplaats — geen Marktplaats, geen Tweakers V&A, wel kabels en RAM"
    description="Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin. Open source, privacy by design, geen cookiebanner-theater."
    :canonical="url('/')"
>

    {{-- ========== HERO ========== --}}
    <section class="border-b border-cmp-border bg-cmp-surface">
        <div class="mx-auto grid max-w-6xl grid-cols-1 items-center gap-10 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[1fr,auto]">
            <div>
                <div class="cmp-section-label mb-6">v0.1.0 · Foundation</div>

                <h1 class="font-display text-4xl font-bold tracking-display-tighter leading-[1.02] sm:text-6xl">
                    {{ __('Geen Marktplaats.') }}<br>
                    {{ __('Geen Tweakers V&A.') }}<br>
                    <span class="text-cmp-signal">{{ __('Wel kabels, RAM en root.') }}</span>
                </h1>

                <p class="mt-6 max-w-xl text-cmp-muted text-base sm:text-lg leading-relaxed">
                    {{ __('Peer-to-peer marktplaats voor servers, netwerkspul, dev boards en alles ertussenin. Plus een anonieme homelab-showcase en reputatie die op door kopers bevestigde deals leunt — geen sterretjes-theater. Open source, privacy by design, geen cookiebanner-theater.') }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('register') }}" class="cmp-btn cmp-btn-primary justify-center">
                        {{ __('Begin met verkopen') }}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                    <a href="{{ route('listings.index') }}" class="cmp-btn cmp-btn-secondary justify-center">
                        {{ __('Bekijk aanbod') }}
                    </a>
                </div>
            </div>

            {{-- De inventarissticker: het signature-element, met de principes
                 als sticker-rijen. Specs, geen marketingclaims. --}}
            <x-inventory-label
                class="w-72 justify-self-center lg:justify-self-end"
                :rows="[
                    __('Licentie')     => 'AGPL-3.0',
                    __('Trackers')     => '0',
                    __('IP-retentie')  => __('24 uur'),
                    __('EXIF')         => __('Gestript'),
                    __('Eigenaar')     => __('Community'),
                ]"
                highlight="{{ __('Trackers') }}"
            />
        </div>
    </section>

    {{-- ========== BETA-COHORT + LIVE STATS (FOMO) ========== --}}
    @if (config('cloudmarktplaats.features.stats'))
        <section class="mx-auto max-w-6xl px-5 sm:px-8 pb-4">
            <livewire:launch-stats />
        </section>
    @endif

    {{-- ========== RECENT LISTINGS — de marktplaats ís de homepage ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <livewire:recent-listings :limit="6" />
    </section>

    {{-- ========== UIT DE HOMELABS ========== --}}
    <section class="mx-auto max-w-6xl px-5 sm:px-8 pb-12">
        <livewire:homelab.recent :limit="3" />
    </section>

    {{-- ========== PRINCIPES ALS DATASHEET ========== --}}
    <section aria-labelledby="features-heading" class="mx-auto max-w-6xl px-5 sm:px-8 pb-16">
        <div class="cmp-section-label mb-3">{{ __('Waar het op rust') }}</div>
        <h2 id="features-heading" class="text-2xl font-bold tracking-display-tight max-w-xl sm:text-3xl">
            {{ __('Drie dingen die we niet als marketing roepen, maar in code zetten.') }}
        </h2>

        @php
            $architectureText = __('IP-adressen gewist na 24 uur. EXIF gestript bij upload. Geen trackers, geen analytics. Kijk maar in de code.');
            $architectureText = str_replace(
                __('code'),
                '<a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="text-cmp-blue underline hover:text-cmp-blue-dark" rel="noopener external">'.__('code').'</a>',
                $architectureText
            );
        @endphp

        <dl class="mt-8 divide-y divide-cmp-border border-y border-cmp-border">
            <div class="grid grid-cols-1 gap-2 py-5 sm:grid-cols-[220px,1fr] sm:gap-8">
                <dt class="font-mono text-sm font-medium uppercase tracking-wide">{{ __('Privacy als architectuur') }}</dt>
                <dd class="text-sm text-cmp-muted leading-relaxed">
                    {!! $architectureText !!}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-2 py-5 sm:grid-cols-[220px,1fr] sm:gap-8">
                <dt class="font-mono text-sm font-medium uppercase tracking-wide">{{ __('Open source') }}</dt>
                <dd class="text-sm text-cmp-muted leading-relaxed">
                    {{ __('AGPL-3.0. Forken mag, zelfhosten mag. Wijzigingen moeten publiek blijven.') }}
                </dd>
            </div>
            <div class="grid grid-cols-1 gap-2 py-5 sm:grid-cols-[220px,1fr] sm:gap-8">
                <dt class="font-mono text-sm font-medium uppercase tracking-wide">{{ __('Community-owned') }}</dt>
                <dd class="text-sm text-cmp-muted leading-relaxed">
                    {{ __('Geen aandeelhouders, geen exit-doel. Winst gaat naar hosting en de mensen die het draaiend houden.') }}
                </dd>
            </div>
        </dl>
    </section>

</x-layouts.marketing>
