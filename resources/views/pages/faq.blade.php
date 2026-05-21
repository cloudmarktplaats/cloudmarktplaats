@php
    // Each FAQ entry: question, answer (HTML allowed), and optional `notice`
    // for legally-sensitive answers (DAC7, liability).
    $faqs = [
        [
            'q' => 'Is dit legaal?',
            'a' => '<p>Ja. Cloudmarktplaats is een advertentiebord tussen particulieren (en kleine bedrijven), vergelijkbaar met Marktplaats of Tweakers V&amp;A. Verkoop tussen consumenten valt onder gewone Nederlandse koop- en consumentenwetgeving. Wij faciliteren ontmoeting, geen transactie.</p>',
        ],
        [
            'q' => 'Wat doen jullie met mijn data?',
            'a' => '<p>Het minimale. We slaan op: e-mailadres, gebruikersnaam, advertenties, foto\'s (zonder EXIF/GPS — die worden bij upload gestript). Je IP-adres bewaren we maximaal 24 uur voor incident-response, daarna wordt het automatisch gewist (<code class="font-mono text-cmp-text text-[12px]">IpStripperJob</code>). We delen niets met derden behalve waar dat wettelijk moet. We hebben geen Google Analytics, geen Facebook Pixel, geen Hotjar, en geen cookiebanner — omdat we geen non-essentiële cookies zetten.</p>',
        ],
        [
            'q' => 'Rapporteren jullie aan de Belastingdienst?',
            'notice' => 'Deze informatie is geen juridisch advies. Zie <code class="font-mono text-cmp-text text-[12px]">docs/dac7-position.md</code> voor de volledige analyse.',
            'a' => '<p>Voor nu: <strong>nee</strong>. De DAC7-richtlijn verplicht platforms om verkopers te rapporteren boven 30 transacties óf €2.000 omzet per kalenderjaar — maar alleen wanneer betalingen via het platform lopen. Bij ons gebeurt afhandeling offline (Tikkie, contant, overschrijving). We zien je transacties niet en kunnen ze dus ook niet rapporteren.</p>
                    <p class="mt-3">Dat verandert pas als we ooit een betaalstroom op-platform aanzetten (de Web3-escrow-module, sub-project #7, staat in de roadmap maar is uitgeschakeld). Op dat moment activeren we ook de DAC7-export en houden we per verkoper de drempel bij. Je krijgt dan ruim van tevoren bericht.</p>',
        ],
        [
            'q' => 'Hoe verdienen jullie geld?',
            'a' => '<p>Donaties, sponsoring (zie sponsorpagina), en op termijn optionele premium listings. Geen advertenties, geen affiliate links, geen verkoop van data. Inkomsten en kosten worden gepubliceerd zodra het structureel boven nul uit komt.</p>',
        ],
        [
            'q' => 'Kan ik betalen met crypto?',
            'a' => '<p>Op dit moment niet. Cloudmarktplaats faciliteert nu zelf geen betalingen — koper en verkoper regelen dat onderling, dus als jullie het allebei in een coin willen doen, prima. Voor de toekomst staat Web3-escrow (smart contract als tussenpartij) in de roadmap, gated achter een feature flag. Als en wanneer dat live gaat, lees je het hier eerst.</p>',
        ],
        [
            'q' => 'Wat als iemand me oplicht?',
            'notice' => 'Foundation is een advertentiebord — geen escrow, geen kopersbescherming. Lees dit aandachtig.',
            'a' => '<p>Cloudmarktplaats is geen escrow en wij houden geen geld vast. Je sluit een koop met een andere gebruiker, niet met ons. Tips: ontmoet lokaal waar het kan, controleer hardware voordat je betaalt, gebruik betaalmethodes met kopersbescherming. Meld misbruik via de "Report"-knop op elk profiel of elke advertentie; we bannen vastgestelde oplichters en de modlog blijft beschikbaar. Reputatie en reviews komen in sub-project #4.</p>',
        ],
        [
            'q' => 'Hoe werkt het reputatiesysteem?',
            'a' => '<p>Nog niet. Reputation/reviews zit in sub-project #4 en is op dit moment uitgeschakeld. Tot die tijd is je trust signal: oudte van het account, aantal advertenties, en — zodra het er is — feedback van eerdere transactiepartners. We bouwen liever een werkend systeem dan een fake aantal sterretjes.</p>',
        ],
        [
            'q' => 'Waarom geen app?',
            'a' => '<p>Cloudmarktplaats werkt als progressive web app: je kan hem aan je beginscherm vastpinnen en hij draait offline waar het kan. Geen native iOS- of Android-app om drie redenen: (1) we hebben er geen capaciteit voor; (2) we willen geen 15% omzet aan Apple of Google afdragen voor een app die niets bijzonders doet; (3) we willen niet dat Apple of Google bepaalt welke advertenties bij ons online mogen. Als de community een eigen app wil bouwen op onze open API: graag.</p>',
        ],
        [
            'q' => 'Hoe kan ik de community steunen?',
            'a' => '<p>In volgorde van impact: (1) plaats advertenties en gebruik het platform — netwerkeffect is wat we nodig hebben; (2) doneer eenmalig of maandelijks; (3) word sponsor of haal je werkgever over; (4) draag code, vertalingen of documentatie bij via GitHub; (5) modereer mee — meld misstanden en help nieuwe gebruikers.</p>',
        ],
        [
            'q' => 'Wat mag ik NIET verkopen?',
            'a' => '<ul class="list-disc list-outside ml-5 space-y-1.5">
                <li>Wapens en munitie (Wwm), inclusief geconverteerde tools die als wapen kunnen dienen.</li>
                <li>Drugs, precursoren, lachgas in commerciële hoeveelheden.</li>
                <li>Gestolen waar — serienummers worden steekproefsgewijs gecontroleerd.</li>
                <li>Counterfeit / nagemaakte merkproducten.</li>
                <li>Software of mediabestanden waarop je geen licentie of doorverkooprecht hebt.</li>
                <li>Persoonsgegevens of databases met persoonsgegevens.</li>
                <li>Spyware, stalkerware, of hardware-implants gericht op heimelijk afluisteren.</li>
                <li>Hardware met actieve, voorgeprogrammeerde malware (dual-use security tools mag, mits eerlijk beschreven).</li>
                <li>Levende dieren, menselijke biomaterialen, of wat verder dan ook in de Telecomwet, Geneesmiddelenwet of Warenwet thuishoort en niet hier.</li>
            </ul>
            <p class="mt-3">In twijfel? Plaats het, en als het over de schreef is hoor je het van de moderatie. We modereren in dialoog, niet met een hakbijl.</p>',
        ],
    ];

    // Build FAQ JSON-LD (strip HTML for the schema text).
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name'  => $f['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => trim(strip_tags($f['a'])),
            ],
        ], $faqs),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

<x-layouts.marketing
    title="Veelgestelde vragen — Cloudmarktplaats"
    description="Tien vragen die je waarschijnlijk hebt, met eerlijke antwoorden. Inclusief de juridisch gevoelige."
    :canonical="url('/faq')"
    :jsonLd="$jsonLd"
>

    <section class="mx-auto max-w-3xl px-5 sm:px-8 py-16 sm:py-20">

        <header class="mb-12">
            <div class="cmp-section-label mb-4">FAQ</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                Tien vragen.<br>
                <span class="text-cmp-muted">Eerlijke antwoorden.</span>
            </h1>
        </header>

        <div x-data="{ open: null }" class="border-t border-cmp-border">
            @foreach ($faqs as $i => $faq)
                <article class="border-b border-cmp-border">
                    <h2>
                        <button
                            type="button"
                            x-on:click="open = open === {{ $i }} ? null : {{ $i }}"
                            x-bind:aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                            aria-controls="faq-panel-{{ $i }}"
                            class="w-full flex items-center justify-between gap-4 py-5 text-left transition-colors hover:text-cmp-blue focus:outline-none focus-visible:ring-2 focus-visible:ring-cmp-blue-light focus-visible:ring-offset-2 focus-visible:ring-offset-cmp-bg"
                        >
                            <span class="flex items-center gap-4">
                                <span class="font-mono text-[11px] text-cmp-blue tracking-widest shrink-0">
                                    {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="text-base sm:text-lg font-semibold tracking-display-tight">
                                    {{ $faq['q'] }}
                                </span>
                            </span>
                            <svg
                                width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="shrink-0 text-cmp-muted transition-transform duration-200"
                                x-bind:class="open === {{ $i }} ? 'rotate-180' : ''"
                                aria-hidden="true">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                    </h2>

                    <div
                        id="faq-panel-{{ $i }}"
                        x-show="open === {{ $i }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="pb-6 pl-0 sm:pl-[44px] text-cmp-text/90 text-[15px] leading-[1.75]"
                        style="display: none;"
                    >
                        @isset($faq['notice'])
                            <div class="mb-4 flex items-start gap-2 rounded-md border border-cmp-amber/40 bg-cmp-amber/10 px-3 py-2.5 text-[13px] text-cmp-amber">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span>{!! $faq['notice'] !!}</span>
                            </div>
                        @endisset

                        {!! $faq['a'] !!}
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('about') }}" class="hover:text-cmp-blue">→ Over ons</a>
            <a href="{{ route('values') }}" class="hover:text-cmp-blue">→ Onze waarden</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="hover:text-cmp-blue" rel="noopener external">→ Een vraag stellen op GitHub</a>
        </div>

    </section>

</x-layouts.marketing>
