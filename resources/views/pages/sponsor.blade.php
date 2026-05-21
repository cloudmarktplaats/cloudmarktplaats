@php
    $tiers = [
        [
            'name'     => 'Community',
            'price'    => '€50',
            'period'   => '/maand',
            'subtitle' => 'Voor zelfstandigen, kleine bedrijven, individuen die het hosting-geld helpen ophoesten.',
            'featured' => false,
            'perks'    => [
                'Vermelding (naam + link) op de sponsorpagina',
                'Toegang tot een privé-Matrix-kanaal met andere sponsors en het kernteam',
                'Goed gevoel',
            ],
        ],
        [
            'name'     => 'Partner',
            'price'    => '€200',
            'period'   => '/maand',
            'subtitle' => 'Voor bedrijven met een actieve betrokkenheid bij de Nederlandse tech-community.',
            'featured' => false,
            'perks'    => [
                'Alles van Community',
                'Logo (vector, ingebed — geen tracking-pixel)',
                'Korte tekst (≤ 60 woorden) over wat je doet',
                'Twee gastposts per jaar op het community-blog',
            ],
        ],
        [
            'name'     => 'Founding Sponsor',
            'price'    => '€500',
            'period'   => '/maand',
            'subtitle' => 'Voor partijen die meer dan een logo willen zijn.',
            'featured' => true,
            'perks'    => [
                'Alles van Partner',
                'Vermelding als founding sponsor in de README van de open source codebase',
                'Stemrecht op de jaarlijkse community-vergadering over uitgaven van het sponsorfonds',
                'Persoonlijke kennismaking met het kernteam',
            ],
        ],
    ];

    $notGetting = [
        'Geen toegang tot gebruikersdata. We hebben hem niet, dus we kunnen hem niet leveren.',
        'Geen native ads, geen sponsored listings, geen voorrang in zoekresultaten.',
        'Geen invloed op moderatiebeslissingen.',
        'Geen exclusiviteit. Concurrenten kunnen óók sponsor zijn — gezonde markt.',
        'Geen exit-traject. Als je stopt met sponsoren, verdwijnt de vermelding aan het eind van de lopende maand. Geen drama, geen contract.',
    ];
@endphp

<x-layouts.marketing
    title="Sponsoring — Cloudmarktplaats"
    description="Sponsor de community, niet onze CPM. Drie tiers, transparant verdienmodel, geen invloed op moderatie."
    :canonical="url('/sponsors')"
>

    <section class="mx-auto max-w-5xl px-5 sm:px-8 py-16 sm:py-20">

        {{-- Amber notice — sponsoring sub-project nog niet live --}}
        <div role="status" class="mb-12 flex items-start gap-3 rounded-lg border border-cmp-amber/40 bg-cmp-amber/10 px-4 py-3 text-sm text-cmp-amber">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <p>
                <strong class="font-semibold">Sponsoring is in opbouw.</strong>
                Interesse? Mail ons — we plannen een gesprek zodra de betalingsinfrastructuur live is.
            </p>
        </div>

        <header class="max-w-2xl mb-14">
            <div class="cmp-section-label mb-4">Sponsoring</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                Sponsor de community,<br>
                <span class="text-cmp-muted">niet ons CPM.</span>
            </h1>

            <p class="mt-6 text-cmp-text/90 text-[15px] leading-[1.75]">
                Sponsoring bij Cloudmarktplaats is geen advertentie. Je krijgt geen banner tussen de
                zoekresultaten, geen native ad in een feed, geen pixel die meeloopt. Je krijgt een
                vermelding — letterlijk, op een aparte pagina — omdat je een marktplaats zonder
                surveillance-kapitalisme overeind helpt houden. Dat is het.
            </p>
            <p class="mt-4 text-cmp-muted text-sm leading-relaxed">
                Sponsors hebben <strong class="text-cmp-text">geen</strong> invloed op moderatie, ranking,
                of welke advertenties geplaatst worden. Dat staat ook in de richtlijnen van het
                moderatieteam en kan publiekelijk gecontroleerd worden via de modlog.
            </p>
        </header>

        {{-- Tier cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach ($tiers as $tier)
                <div class="relative rounded-xl bg-cmp-surface p-6 flex flex-col {{ $tier['featured'] ? 'border-2 border-cmp-blue' : 'border border-cmp-border' }}">
                    @if ($tier['featured'])
                        <span class="absolute -top-3 left-6 font-mono text-[10px] tracking-widest bg-cmp-blue text-white px-2 py-1 rounded">
                            FEATURED
                        </span>
                    @endif

                    <div>
                        <h2 class="text-xl font-bold tracking-display-tight">{{ $tier['name'] }}</h2>
                        <div class="mt-3 flex items-baseline">
                            <span class="text-3xl font-bold tracking-display-tight">{{ $tier['price'] }}</span>
                            <span class="ml-1 font-mono text-[11px] text-cmp-muted">{{ $tier['period'] }}</span>
                        </div>
                        <p class="mt-4 text-sm text-cmp-muted leading-relaxed">{{ $tier['subtitle'] }}</p>
                    </div>

                    <ul class="mt-6 space-y-3 flex-1">
                        @foreach ($tier['perks'] as $perk)
                            <li class="flex items-start gap-2 text-sm">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00FF88" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span class="text-cmp-text/90">{{ $perk }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a href="mailto:sponsoring@cloudmarktplaats.nl?subject=Sponsoring%20-%20{{ rawurlencode($tier['name']) }}"
                       class="mt-8 cmp-btn {{ $tier['featured'] ? 'cmp-btn-primary' : 'cmp-btn-secondary' }} justify-center">
                        Mail ons over {{ $tier['name'] }}
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Wat sponsors NIET krijgen --}}
        <section class="mt-16 rounded-xl border border-cmp-border bg-cmp-bg2 p-8">
            <div class="cmp-section-label mb-4">Niet</div>
            <h2 class="text-2xl font-bold tracking-display-tight">Wat sponsors <span class="text-cmp-muted">niet</span> krijgen</h2>

            <ul class="mt-6 space-y-3">
                @foreach ($notGetting as $item)
                    <li class="flex items-start gap-3 text-sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FF4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        <span class="text-cmp-muted line-through decoration-cmp-faint/60 decoration-1">{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Interesse --}}
        <section class="mt-16 text-center">
            <h2 class="text-2xl font-bold tracking-display-tight">Interesse?</h2>
            <p class="mt-3 max-w-xl mx-auto text-cmp-muted text-sm leading-relaxed">
                Mail <a href="mailto:sponsoring@cloudmarktplaats.nl" class="text-cmp-blue hover:text-cmp-blue-light underline underline-offset-4">sponsoring@cloudmarktplaats.nl</a>.
                We sturen je geen verkoopdeck — wel een eerlijk gesprek over wat je van een sponsorship
                verwacht en of dat klopt bij wat we kunnen bieden.
            </p>
        </section>

    </section>

</x-layouts.marketing>
