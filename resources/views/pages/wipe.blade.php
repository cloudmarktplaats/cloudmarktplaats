@php
    // De "hoe" achter regel 1 van /wat-mag-erop ("Data eraf, altijd"). Die regel
    // stond er maanden zonder dat er ergens stond hoe je het doet, en dat is
    // precies het soort belofte zonder code eronder waar dit platform op wordt
    // afgerekend. Peter HJ van Eijk vroeg er publiek naar op 09-09-2026.
    //
    // De commando's hieronder vernietigen data. Blijf bij de volgorde: eerst
    // vaststellen wélke schijf, dan pas wissen. De disclaimer staat bewust
    // bovenaan en niet in de voettekst.

    $stappen = [
        [
            __('Stel vast welke schijf het is'),
            __('/dev/sdX bestaat niet. Letters verschuiven tussen herstarts, dus controleer het serienummer tegen de sticker op de schijf zelf.'),
            "lsblk -o NAME,SIZE,MODEL,SERIAL",
        ],
        [
            __('Lees de schijf uit voordat je hem wist'),
            __('Draaiuren en herverdeelde sectoren horen in je advertentie. Na het wissen kun je ze nog steeds lezen, maar dan weet je niet meer welke schijf het was.'),
            "sudo smartctl -a /dev/sda | grep -E 'Power_On_Hours|Reallocated_Sector|Current_Pending'",
        ],
        [
            __('Wis, met de methode die bij het type schijf hoort'),
            __('Een HDD en een SSD vragen om iets anders. Zie hieronder welke bij jouw schijf hoort. Dit is de stap zonder weg terug.'),
            null,
        ],
        [
            __('Controleer dat er nullen staan'),
            __('Geen bewijs, wel de goedkoopste manier om te merken dat je de verkeerde schijf te pakken had of dat er niets gebeurd is.'),
            "sudo dd if=/dev/sda bs=1M count=100 status=none | hexdump -C | head",
        ],
    ];

    // Arjan Krabbendam vroeg op 10-09-2026 hoe je draaiuren uitleest bij de
    // schijven waarbij dat "echt niet meer" lukt. Dat is bijna nooit een schijf
    // zonder SMART: er zit iets tussen dat de gegevens niet doorgeeft. Stap 2
    // had precies 1 regel en geen enkele terugval, dus wie hier vastliep haakte
    // af zonder te weten dat het aan de kabel lag.
    $smartTerugvallen = [
        [
            __('Een USB-behuizing ertussen'),
            __('De brug in het dockingstation geeft SMART-opdrachten vaak niet door. Met -d sat stuur je ze er alsnog doorheen. Weet je het type niet: smartctl --scan noemt het.'),
            'sudo smartctl -a -d sat /dev/sdb',
        ],
        [
            __('Achter een RAID-controller'),
            __('Een PERC of LSI laat het besturingssysteem de array zien en niet de schijven. Het nummer achter megaraid is de fysieke slot, dus tel omhoog tot je hem hebt. Een HP Smart Array gebruikt cciss in plaats van megaraid.'),
            'sudo smartctl -a -d megaraid,0 /dev/sda',
        ],
        [
            __('NVMe'),
            __('NVMe kent de ATA-attributen niet. Het getal heet hier gewoon power_on_hours.'),
            'sudo nvme smart-log /dev/nvme0',
        ],
        [
            __('SAS in plaats van SATA'),
            __('Een SAS-schijf heeft geen attribuut 9. Het staat er als "Accumulated power on time, hours:minutes", verderop in dezelfde uitvoer.'),
            'sudo smartctl -a /dev/sdb | grep -i "power on time"',
        ],
    ];

    $methodes = [
        [
            __('HDD (magnetisch)'),
            __('1 pass met nullen. NIST SP 800-88 Rev. 1 noemt dat voldoende voor elke schijf van na 2001. Bereikt geen herverdeelde sectoren; wil je die ook, gebruik dan Secure Erase.'),
            "sudo shred -n 0 -z -v /dev/sda",
        ],
        [
            __('SSD en HDD via SATA (Secure Erase)'),
            __('De firmware wist zichzelf, inclusief reservesectoren. Eerst een wachtwoord zetten, anders weigert de schijf. Duurt seconden tot minuten.'),
            "sudo hdparm --user-master u --security-set-pass p /dev/sda\nsudo hdparm --user-master u --security-erase p /dev/sda",
        ],
        [
            __('NVMe'),
            __('Crypto erase (-s 2) gooit de interne sleutel weg en is klaar voor je koffie koud is. Ondersteunt je schijf dat niet, gebruik dan -s 1.'),
            "sudo nvme id-ctrl /dev/nvme0 -H | grep -i 'crypto erase'\nsudo nvme format /dev/nvme0n1 -s 2",
        ],
        [
            __('NAS (Synology, QNAP)'),
            __('De data zit in de schijven, niet in het kastje. Een fabrieksreset van de NAS laat de schijven vol staan, en de configuratiepartitie staat op álle schijven tegelijk. Haal ze eruit en behandel ze los.'),
            null,
        ],
        [
            __('Versleuteld vanaf dag 1'),
            __('LUKS of BitLocker vanaf het begin is de goedkoopste variant van dit hele verhaal: de sleutel weggooien maakt de rest onleesbaar. Achteraf versleutelen doet dat niet, want de oude blokken staan er dan nog.'),
            "sudo cryptsetup luksErase /dev/sda",
        ],
    ];

    $nietDoen = [
        [__('Snel formatteren of de partitie verwijderen'), __('Dat wist de inhoudsopgave, niet de data. Een herstelprogramma van 20 euro haalt het terug.')],
        [__('7 of 35 keer overschrijven'), __('DoD 5220.22-M en Gutmann komen uit het MFM/RLL-tijdperk. Op moderne schijven kost het een dag en levert het niets op boven 1 pass.')],
        [__('Een SSD overschrijven'), __('Door wear leveling en over-provisioning schrijf je niet naar de blokken waar je data staat. Gebruik Secure Erase of crypto erase.')],
        [__('Een SSD degaussen'), __('Er is niets magnetisch aan. De schijf blijft leesbaar.')],
        [__('Een schijf met kapotte elektronica "even" wissen'), __('Reageert hij nergens op, dan kun je hem ook niet wissen. Verkoop hem als defect zonder de schijf zelf, of laat hem vernietigen.')],
    ];
@endphp

<x-layouts.marketing
    :title="__('Data van je schijf wissen — Cloudmarktplaats')"
    :description="__('Vier stappen, de juiste methode per schijftype, en wat niet werkt. Voor je een schijf verkoopt.')"
    :canonical="url('/data-wissen')"
>

    <section class="mx-auto max-w-4xl px-5 sm:px-8 py-16 sm:py-20">

        <header class="mb-10">
            <div class="cmp-section-label mb-4">{{ __('Data eraf') }}</div>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-display-tighter leading-[1.05]">
                {{ __('Wissen is niet') }}<br>
                <span class="text-cmp-muted">{{ __('hetzelfde als formatteren.') }}</span>
            </h1>
            <p class="mt-6 max-w-2xl text-cmp-muted leading-relaxed">
                {{ __('Op /wat-mag-erop staat de regel: data eraf, altijd, en zet in je advertentie hoe je het gedaan hebt. Deze pagina is de "hoe". Vier stappen, en per schijftype een andere methode, want wat op een harde schijf werkt doet op een SSD niets.') }}
            </p>
        </header>

        {{-- Bovenaan, niet onderaan. Wie hieronder een commando kopieert en de
             verkeerde letter pakt, is zijn eigen data kwijt. --}}
        <div class="mb-14 rounded-sm border-2 border-cmp-border bg-cmp-bg2 p-6">
            <h2 class="text-base font-bold tracking-display-tight">{{ __('Lees dit eerst') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-cmp-muted leading-relaxed" role="list">
                <li>{{ __('Deze commando\'s vernietigen alles op de genoemde schijf. Er is geen prullenbak en geen ongedaan maken.') }}</li>
                <li>{{ __('/dev/sdX en /dev/sda zijn voorbeelden, geen adres van jouw schijf. Controleer het serienummer voordat je iets uitvoert, en controleer of je niet op je eigen systeemschijf zit.') }}</li>
                <li>{{ __('Dit is een homelab-handleiding van 1 onbetaalde beheerder, geen gecertificeerde procedure. Wij geven geen enkele garantie dat dit compleet of juist is voor jouw hardware, firmware of situatie. Je voert het uit op eigen risico; Cloudmarktplaats en de beheerder aanvaarden geen aansprakelijkheid voor dataverlies of schade.') }}</li>
                <li>{{ __('Stond er data van klanten, medewerkers of patiënten op? Dan is dit niet genoeg. Onder de AVG blijf jij verwerkingsverantwoordelijke, ook nadat de schijf verkocht is, en een schijf die de deur uit gaat zonder aantoonbare sanering is een datalek dat bij jou ligt. Gebruik een verwerker die je een certificaat levert.') }}</li>
                <li>{{ __('Twijfel je? Verkoop de schijf dan niet. Een schijf is 20 euro waard en een datalek een veelvoud daarvan.') }}</li>
            </ul>
        </div>

        <h2 class="cmp-section-label mb-4">{{ __('Vier stappen, in deze volgorde') }}</h2>
        <ol class="mb-14 border-t border-cmp-border" role="list">
            @foreach ($stappen as $i => [$title, $body, $cmd])
                <li class="border-b border-cmp-border py-5">
                    <div class="flex gap-4 items-baseline">
                        <span class="font-mono text-[11px] text-cmp-blue tracking-widest shrink-0 w-8">
                            {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold tracking-display-tight">{{ $title }}</h3>
                            <p class="text-sm text-cmp-muted mt-1 leading-relaxed">{{ $body }}</p>
                            @if ($cmd)
                                <pre class="mt-3 overflow-x-auto rounded-sm bg-cmp-ink p-3 font-mono text-xs text-white"><code>{{ $cmd }}</code></pre>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Hoort bij stap 2, en staat daarom direct achter de stappen. --}}
        <div class="mb-14 rounded-sm border border-cmp-border bg-cmp-bg2 p-6">
            <h2 class="text-base font-bold tracking-display-tight">{{ __('En dan geeft smartctl niets terug') }}</h2>
            <p class="mt-2 text-sm text-cmp-muted leading-relaxed">
                {{ __('Dat ligt bijna nooit aan de schijf. Er zit iets tussen dat de opdracht niet doorgeeft, en dan is de vraag welke omweg je neemt.') }}
            </p>
            <dl class="mt-4 space-y-4">
                @foreach ($smartTerugvallen as [$geval, $uitleg, $cmd])
                    <div class="border-t border-cmp-border pt-3">
                        <dt class="text-sm font-bold tracking-display-tight">{{ $geval }}</dt>
                        <dd class="text-sm text-cmp-muted mt-1 leading-relaxed">{{ $uitleg }}</dd>
                        <dd><pre class="mt-2 overflow-x-auto rounded-sm bg-cmp-ink p-3 font-mono text-xs text-white"><code>{{ $cmd }}</code></pre></dd>
                    </div>
                @endforeach
            </dl>
            {{-- Een eerlijk "weet ik niet" is bruikbaarder dan een schatting die
                 de koper niet kan controleren. Dat is dezelfde regel als op
                 /wat-mag-erop: beschrijf de staat inclusief het vervelende deel. --}}
            <p class="mt-5 text-sm text-cmp-muted leading-relaxed">
                {{ __('Komt er dan nog niets uit, zet dan "draaiuren onbekend" in je advertentie. Dat is een geldig antwoord. Een koper kan een schatting namelijk niet controleren, en een getal dat je erbij verzint kost je precies het vertrouwen waarvoor hij hier komt.') }}
            </p>
        </div>

        <h2 class="cmp-section-label mb-4">{{ __('Stap 3, per type schijf') }}</h2>
        <div class="mb-6 grid grid-cols-1 gap-4">
            @foreach ($methodes as [$name, $body, $cmd])
                <div class="rounded-sm border border-cmp-border bg-cmp-surface p-6">
                    <h3 class="text-base font-bold tracking-display-tight">{{ $name }}</h3>
                    <p class="text-sm text-cmp-muted mt-2 leading-relaxed">{{ $body }}</p>
                    @if ($cmd)
                        <pre class="mt-3 overflow-x-auto rounded-sm bg-cmp-ink p-3 font-mono text-xs text-white"><code>{{ $cmd }}</code></pre>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Het antwoord op "frozen" hoort hier en niet in een voetnoot: het is
             de plek waar iedereen die dit voor het eerst doet vastloopt. --}}
        <div class="mb-14 rounded-sm border border-cmp-border bg-cmp-bg2 p-6">
            <h3 class="text-base font-bold tracking-display-tight">{{ __('En dan zegt hdparm "frozen"') }}</h3>
            <p class="text-sm text-cmp-muted mt-2 leading-relaxed">
                {{ __('Dat doet je BIOS, om te voorkomen dat software zomaar een schijf wist. Slaapstand in en er weer uit haalt de vergrendeling eraf, en dat is veiliger dan het alternatief dat je overal leest (de SATA-kabel eruit trekken en er weer in duwen terwijl de machine aan staat).') }}
            </p>
            <pre class="mt-3 overflow-x-auto rounded-sm bg-cmp-ink p-3 font-mono text-xs text-white"><code>sudo hdparm -I /dev/sda | grep -i frozen
sudo systemctl suspend</code></pre>
        </div>

        <h2 class="cmp-section-label mb-4">{{ __('Wat niet werkt') }}</h2>
        <ul class="border-t border-cmp-border mb-14" role="list">
            @foreach ($nietDoen as [$wat, $waarom])
                <li class="border-b border-cmp-border py-4 flex gap-4 items-baseline">
                    <span class="font-mono text-[11px] tracking-widest shrink-0 w-8 text-cmp-muted">{{ __('NEE') }}</span>
                    <span>
                        <span class="text-sm font-bold tracking-display-tight">{{ $wat }}</span>
                        <span class="text-sm text-cmp-muted"> {{ $waarom }}</span>
                    </span>
                </li>
            @endforeach
        </ul>

        <h2 class="cmp-section-label mb-4">{{ __('Wat je in je advertentie zet') }}</h2>
        <p class="max-w-2xl text-cmp-muted leading-relaxed">
            {{ __('Vier regels zijn genoeg: hoe je gewist hebt, hoeveel draaiuren erop staan, hoeveel herverdeelde sectoren er zijn, en wat SMART als eindoordeel geeft. Wie hier een tweedehands schijf koopt, koopt vooral zekerheid over wat hij krijgt. Die zekerheid is meer waard dan de tien euro die je van de prijs afhaalt.') }}
        </p>

        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('scope') }}" class="hover:text-cmp-blue">{{ __('→ Wat mag erop?') }}</a>
            <a href="{{ route('listings.create') }}" class="hover:text-cmp-blue">{{ __('→ Plaats je schijf') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/issues" class="hover:text-cmp-blue" rel="noopener external">{{ __('→ Klopt er iets niet? Meld het') }}</a>
        </div>

    </section>

</x-layouts.marketing>
