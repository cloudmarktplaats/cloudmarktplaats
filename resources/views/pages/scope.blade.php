@php
    // Positieve scope. De categorieboom in CategorySeeder is de bron; deze
    // pagina is de leesbare versie plus de randgevallen die daar niet uit
    // volgen. Wat NIET mag (wapens, gestolen waar, counterfeit) staat in de
    // FAQ — dat is juridisch, dit is redactioneel.
    $tests = [
        [__('Categorietest'), __('Past het in een van de twaalf categorieën? Dan plaatsen, klaar.')],
        [__('Doeltest'), __('Koop je dit om ermee te bouwen, meten, draaien of aansluiten — of om ermee te consumeren? Bouwen mag. Een beamer voor een serverruimte: ja. Een tv voor de bank: nee.')],
        [__('Ruistest'), __('Is het los een advertentie waard? Eén patchkabel is ruis. Een doos van veertig is een kavel.')],
    ];

    $categories = [
        [__('Server hardware'), __('Rack- en towerservers, blades, serveronderdelen, rails, racks')],
        [__('Networking'), __('Switches, routers, firewalls, access points, NIC\'s, transceivers, VoIP')],
        [__('Storage'), __('SSD, HDD, NVMe, NAS, SAN, RAID/HBA-controllers, tape')],
        [__('Compute'), __('CPU, GPU, RAM, moederborden, koeling, behuizingen, mini-PC\'s, laptops, workstations, thin clients, dev boards, monitoren, KVM\'s, randapparatuur')],
        [__('Kabels & connectoren'), __('Netwerk-, stroom-, data-, SAS- en SATA-kabels, adapters en converters')],
        [__('Power'), __('Voedingen, UPS\'en, PDU\'s')],
        [__('Audio/Video pro'), __('Beamers, signage, mengpanelen, microfoons, camera\'s, encoders')],
        [__('Meetapparatuur'), __('Oscilloscopen, multimeters, labvoedingen, logic analyzers, soldeerstations')],
        [__('3D printers & CNC'), __('FDM, resin, CNC en lasers, filament, printeronderdelen')],
        [__('Software licenties'), __('Alleen als ze overdraagbaar zijn')],
        [__('Boeken & documentatie'), __('Handboeken, cursusmateriaal, oude documentatie')],
        [__('Overig'), __('Wat er echt niet in past maar wel in een lab hoort')],
    ];

    // De gevallen waar mensen ons daadwerkelijk naar vroegen.
    $edgeCases = [
        [true,  __('Losse desktop-CPU'), __('Iemands eerste NAS of firewall begint hier.')],
        [true,  __('Oude gaming-GPU'), __('Draait nu inference of transcoding. Vermeld het verbruik.')],
        [true,  __('3D-printer'), __('Eigen categorie.')],
        [true,  __('Meetapparatuur uit de jaren 70'), __('De werkbank telt. Leeftijd diskwalificeert niet.')],
        [true,  __('Laptop, monitor, toetsenbord'), __('Een thin client met scherm is een lab-werkplek. Randapparatuur graag gebundeld.')],
        [true,  __('Defect apparaat voor onderdelen'), __('Graag — zet "defect" in de titel.')],
        [true,  __('Kavel: "doos met SFP\'s, doe een bod"'), __('Aangemoedigd. Dit is de makkelijkste manier om te beginnen.')],
        [true,  __('Flipper Zero, RF- en pentest-hardware'), __('Legaal bezit, lab-doel. Geen gebruiksinstructies in de advertentie.')],
        [false, __('Losse kabel van twee euro'), __('Ruistest. Bundel ze tot een kavel, dan mag het wel.')],
        [false, __('Spelcomputer of gamecontroller'), __('Consumeren, niet bouwen. Ook als je er Linux op draaide.')],
        [false, __('Scart-, tulp- en huis-tuin-en-keukenkabels'), __('Consumentenelektronica zonder lab-toepassing.')],
        [false, __('De modem van je provider'), __('Die is niet van jou om door te verkopen.')],
    ];

    $rules = [
        [__('Data eraf, altijd.'), __('Schijven, NAS\'en, firewalls, switches en servers gaan gewist en fabrieksreset de deur uit. Zet in de advertentie hóé je het gewist hebt. Vind je een apparaat met data van iemand anders erop: meld het, plaats het niet.')],
        [__('Beschrijf de staat eerlijk, inclusief het vervelende deel.'), __('Dode UPS-accu, ontbrekende caddies, luidruchtige fans, verbruik in watt. De koper hier is een bouwer, geen impulskoper — die wil weten wat hij binnenhaalt.')],
        [__('Licenties alleen als ze overdraagbaar zijn.'), __('OEM-Windows plakt aan het moederbord en verhuist niet mee. Verkoop de hardware, niet een licentie die de koper nooit krijgt.')],
        [__('Verkoop je bedrijfsmatig, zeg dat erbij.'), __('Voor een bedrijf dat uitfaseert gelden andere regels richting een particuliere koper dan voor iemand met één doos op zolder.')],
    ];
@endphp

<x-layouts.marketing
    :title="__('Wat mag erop? — Cloudmarktplaats')"
    :description="__('Eén regel, drie tests en de randgevallen waar mensen ons naar vroegen.')"
    :canonical="url('/wat-mag-erop')"
>

    <section class="mx-auto max-w-4xl px-5 sm:px-8 py-16 sm:py-20">

        <header class="mb-14">
            <div class="cmp-section-label mb-4">{{ __('Scope') }}</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                {{ __('Hoort het in een lab,') }}<br>
                <span class="text-cmp-muted">{{ __('dan hoort het hier.') }}</span>
            </h1>
            <p class="mt-6 max-w-2xl text-cmp-muted leading-relaxed">
                {{ __('Een serverkast, een netwerk, een werkbank — als het daar thuishoort, mag het erop. Dat is de hele regel. De rest van deze pagina is er alleen voor de randgevallen.') }}
            </p>
        </header>

        <h2 class="cmp-section-label mb-4">{{ __('Twijfel je? Drie tests.') }}</h2>
        <ol class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-14" role="list">
            @foreach ($tests as $i => [$title, $body])
                <li class="rounded-sm border border-cmp-border bg-cmp-surface p-6">
                    <div class="font-mono text-[11px] text-cmp-blue tracking-widest mb-3">
                        {{ str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <h3 class="text-base font-bold tracking-display-tight">{{ $title }}</h3>
                    <p class="text-sm text-cmp-muted mt-2 leading-relaxed">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        <h2 class="cmp-section-label mb-4">{{ __('De categorieën') }}</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-14">
            @foreach ($categories as [$name, $body])
                <div class="border-t border-cmp-border pt-3">
                    <dt class="text-sm font-bold tracking-display-tight">{{ $name }}</dt>
                    <dd class="text-sm text-cmp-muted mt-1 leading-relaxed">{{ $body }}</dd>
                </div>
            @endforeach
        </dl>

        <h2 class="cmp-section-label mb-4">{{ __('De randgevallen, met het antwoord erbij') }}</h2>
        <ul class="border-t border-cmp-border mb-14" role="list">
            @foreach ($edgeCases as [$allowed, $case, $why])
                <li class="border-b border-cmp-border py-4 flex gap-4 items-baseline">
                    <span class="font-mono text-[11px] tracking-widest shrink-0 w-8 {{ $allowed ? 'text-cmp-blue' : 'text-cmp-muted' }}">
                        {{ $allowed ? __('JA') : __('NEE') }}
                    </span>
                    <span>
                        <span class="text-sm font-bold tracking-display-tight">{{ $case }}</span>
                        <span class="text-sm text-cmp-muted"> — {{ $why }}</span>
                    </span>
                </li>
            @endforeach
        </ul>

        <h2 class="cmp-section-label mb-4">{{ __('Vier regels die geen scope zijn, maar wel gelden') }}</h2>
        <ol class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-14" role="list">
            @foreach ($rules as [$title, $body])
                <li class="rounded-sm border border-cmp-border bg-cmp-surface p-6">
                    <h3 class="text-base font-bold tracking-display-tight">{{ $title }}</h3>
                    <p class="text-sm text-cmp-muted mt-2 leading-relaxed">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        <p class="max-w-2xl text-cmp-muted leading-relaxed">
            {{ __('Twijfel je na drie tests nog steeds? Plaats het. Je advertentie staat meteen online; gaat het over de schreef, dan horen we het via een melding en nemen we contact op — dat kost jou minder moeite dan dat het platform leeg blijft. En deze lijst verandert via een issue op GitHub, in het openbaar: de eerste honderd bepalen de cultuur, dat gold voor de toon en het geldt ook hier.') }}
        </p>

        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('faq') }}" class="hover:text-cmp-blue">{{ __('→ Wat mag je NIET verkopen') }}</a>
            <a href="{{ route('values') }}" class="hover:text-cmp-blue">{{ __('→ Onze waarden') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="hover:text-cmp-blue" rel="noopener external">{{ __('→ Stel een wijziging voor') }}</a>
        </div>

    </section>

</x-layouts.marketing>
