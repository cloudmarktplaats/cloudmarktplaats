# Hoe we beslissen, en wie er iets over te zeggen heeft

**Datum:** 2026-07-15
**Status:** ter review

## Waarom

Op `/waarden` staat, live en publiek:

> **De community bezit het platform.** Geen aandeelhouders, geen exit, geen overname.

Op `/sponsors` staat, even live:

> Stemrecht op de jaarlijkse community-vergadering over uitgaven van het sponsorfonds

Leden hebben niets. Geen stem, geen proces, geen plek waar ze iets kunnen vinden van een keuze. Zoals het er nu staat is de enige stem in deze community te koop — precies het model waar dit platform tegen bestaat. Niemand heeft dat zo bedoeld; het is ontstaan doordat de sponsorpagina een concreet aanbod nodig had en de waardenpagina een principe verwoordde.

Dat gat dichten we hier: niet met een stemsysteem, maar door op te schrijven wat waar is en dat ook te doen.

## Waar we staan

Meting op productie, 15-07:

```
leden: 108 | met een GitHub-account: 17 (16%) | advertenties: 10 | homelabs: 2
```

Drie dingen volgen hieruit, en ze sturen elke beslissing hieronder.

**Consulteren op GitHub bereikt één op de zes leden.** De rest kwam via LinkedIn binnen en heeft op GitHub niets te zoeken. Een RFC-proces dat daar begint en eindigt praat met 16% en noemt dat "de community".

**De community heeft nog nauwelijks iets gemaakt.** Honderdacht leden, tien advertenties, twee homelabs. Governance bouwen voor mensen die nog niet meedoen is een parlement inrichten voordat er inwoners zijn.

**Maar gevraagd worden kan meedoen veroorzaken.** Dat is de weddenschap achter deze spec, en het mooie is: die is te testen zonder iets te bouwen.

## Beslissing 1 — consultatie, geen stemrecht

Keuzes gaan openbaar vóórdat ze vastliggen. Wie wil kan reageren. Nick beslist, en legt uit wat hij met de reacties deed — ook, en vooral, als hij afwijkt.

Waarom geen bindende stemming: dit project heeft een uitgesproken smaak ("datasheet, geen startup", "geen trackers", "geen algoritmische manipulatie"). Precies die smaak is stembaar — en de eerste keer dat een meerderheid "kunnen we niet gewoon aanbevelingen doen" wint, is het platform weg waarvoor mensen kwamen. Een stemming die je alleen respecteert als je het ermee eens bent is geen stemming maar theater; dan is consultatie eerlijker.

**De prijs, en die staat hier expliciet:** dit is geen zeggenschap. Consultatie zonder gevolg is erger dan niets vragen — mensen die drie keer gehoord worden en drie keer overruled, komen niet terug. De uitleg is daarom geen beleefdheid maar de hele afspraak: wat er met je reactie gebeurde, en waarom.

## Beslissing 2 — de founding-badge blijft eervol en machteloos

Geen stemrecht, geen vetorecht, geen voorrang, geen eigen kanaal.

De badge zegt *wanneer je binnenkwam*, niet *wat je bijdraagt*. Hang je er invloed aan, dan krijgt lid #43 dat nooit iets postte meer te zeggen dan lid #137 die drie homelabs plaatst en een bug meldt. Dat is een aristocratie op aankomsttijd, en het spreekt het uitgangspunt van dit project tegen: een community die bijdraagt — met tijd, advertenties, homelabs of code.

De badge blijft wat hij is: een eerlijk historisch feit. Dat is genoeg.

## Beslissing 3 — niet alles gaat naar de community

Er is verschil tussen *wat er waar is* en *waar we heen gaan*.

**Nooit ter consultatie** — dit is werk, geen mening:

- bugs en kapotte dingen;
- privacy, beveiliging, AVG;
- eerlijkheid van wat we tonen (een teller die liegt gaat weg, ook als iemand hem mooi vindt);
- wat wettelijk moet;
- individuele moderatiebesluiten (die zijn appellabel via de bestaande route, niet stembaar — anders wordt moderatie een populariteitswedstrijd over een echt persoon).

**Wel ter consultatie:** richting, functionaliteit, geld, waarden, en de vraag wat we níet bouwen.

Zonder die grens verdrinkt de echte vraag in ruis. Illustratie uit deze week: de foto-upload die stil faalde en de verkoper de schuld gaf. Daar stem je niet over. Dat repareer je.

## Beslissing 4 — waar het gebeurt

Geen nieuw platform, geen `/rfc`-pagina, niets te bouwen. De specs staan al openbaar in de repo, in het Nederlands, mét het waarom — dat is bij toeval al een RFC.

Per keuze die ertoe doet:

1. De spec staat in `docs/superpowers/specs/` — dat is de bron, en die verandert niet.
2. Een **LinkedIn-post** die het in gewone taal samenvat en om reacties vraagt. Dáár zitten je leden.
3. Een **GitHub-draad** voor wie de details in wil. Dat is 16%, en dat is prima — zolang het niet de enige deur is.
4. Reacties tellen, ongeacht waar ze binnenkomen: LinkedIn, GitHub, e-mail, of een DM.
5. Nick beslist en publiceert wat hij ermee deed.

Stap 5 is het enige dat niet mag ontbreken. De rest is logistiek.

## Beslissing 5 — `/waarden` gaat zeggen wat er waar is

De zin "De community bezit het platform" is niet gelogen: de code is AGPL, er zijn geen aandeelhouders, er is geen exit, en iedereen kan het hele ding forken en zelf draaien. Dat ís een vorm van eigenaarschap — het recht om weg te lopen met alles.

Maar hij nodigt uit tot een lezing die niet waar is: dat de community beslist. Naast een sponsorpagina die stemrecht verkoopt, wordt die lezing ronduit pijnlijk.

De regel wordt daarom expliciet over wat het wél en niet betekent. Dit is de tekst, letterlijk over te nemen — de `$values`-array in `resources/views/pages/values.blade.php` bevat per punt een kop en een uitleg:

**Kop:** `De community bezit het platform.`

**Uitleg:** `Geen aandeelhouders, geen exit, geen overname. De code is AGPL: je kunt hem forken en zonder ons verder. Keuzes leggen we openbaar voor en we vertellen wat we ermee deden — maar we stemmen er niet over. Eigenaarschap is hier het recht om weg te lopen met alles, niet het recht om ons te overstemmen.`

De kop blijft dus staan; alleen de uitleg eronder wordt eerlijk. Dat is bewust: de kop is waar.

Dat is minder groots en meer waar. Bij een project dat "we schreeuwen niet over privacy in headers, we bouwen het" als waarde heeft, is dat de enige houdbare kant.

**Het sponsor-stemrecht blijft zoals het is.** Sponsors beslissen over het sponsorfonds — over het geld dat zij erin stoppen, en over niets anders. Dat is verdedigbaar zodra het naast een leden-consultatie staat in plaats van erboven. (`FEATURE_SPONSORING` staat bovendien op `false`: het fonds bestaat nog niet. Dit is een belofte over de toekomst, geen praktijk.)

## De proef

De homelab-spec (`2026-07-15-homelab-eigen-pagina-design.md`) is de eerste. Hij is er klaar voor: hij gaat over de community's eigen hoek van de site, hij is leesbaar, en hij bevat echte keuzes waar mensen iets van kunnen vinden — anoniem blijven of niet, wel of geen downvotes, vrije tekst of specs-velden.

- **Duur:** twee weken.
- **Wat telt als reactie:** iemand die inhoudelijk iets vindt van een keuze in de spec. Een like is geen reactie.
- **Wat Nick daarna doet:** publiceren wat er binnenkwam, wat hij overneemt, en wat niet en waarom. Ook bij nul reacties.

**Wat we ervan leren, en wat we dan doen:**

| reacties | conclusie | volgende stap |
|---|---|---|
| 0–3 | Geen animo. Vragen is niet het probleem, meedoen wel. | Niets bouwen. Vraag blijft: waarom laten mensen hun rack niet zien? |
| 4–10 | Er is een kern. | Zo doorgaan, per spec. Nog steeds niets bouwen. |
| 10+ | Er is een community die iets wil. | Nu pas nadenken over een plek op de site zelf. |

Nul reacties is geen mislukking van de proef. Het is het antwoord.

## Wat er niet in zit

- **Een stemsysteem.** Niet bouwen tot bewezen is dat er iemand zou stemmen.
- **Een `/rfc`-pagina op de site.** Idem. LinkedIn en GitHub bestaan al.
- **Een stichting, bestuur of vereniging.** Ver buiten deze vraag.
- **Wijziging van het sponsor-stemrecht.** Dat gaat over geld dat sponsors zelf inleggen, en het fonds bestaat nog niet.
- **Privileges voor founding members.** Zie Beslissing 2; die komen er niet.

## Risico's

**Consultatie kan als schijninspraak voelen.** Het echte risico. Het verschil zit volledig in stap 5: uitleggen wat je met een reactie deed. Sla je dat over, dan is dit erger dan niets vragen — dan heb je mensen om hun mening gevraagd en ze genegeerd, en dat vergeven nerds niet.

**Herformuleren van "bezit" kan als terugkrabbelen lezen.** Het is het tegenovergestelde — de zin wordt preciezer, niet kleiner — maar dat moet je wel zeggen. Bij de aankondiging hoort: dit betekende altijd al AGPL en het recht om te forken, en nu staat het er ook zo.

**Nul reacties is een klap.** Reken erop. Met 10 advertenties en 2 homelabs in een gemeenschap van 108 is stilte de waarschijnlijkste uitkomst. Dat is informatie: het zegt dat het probleem niet zeggenschap is maar deelname, en dan weet je waar de volgende avond heen moet.

**Consultatie kost tempo.** Twee weken wachten op reacties is twee weken niet bouwen. Daarom geldt Beslissing 3 hard: alleen richting gaat de deur uit, werk niet.

## Testbaarheid

Er valt hier weinig te testen, want er wordt weinig gebouwd. Wat wel:

- `/waarden` toont de nieuwe tekst, en de Engelse vertaling bestaat en resolvet (via tinker, niet curl — de locale is sessie-gebonden).
- `docs/GOVERNANCE.md` bestaat, beschrijft Beslissing 1 tot en met 4, en is vanaf `/waarden` te vinden.
- De waardenpagina blijft de overige elf punten tonen (regressie: er staat een `$values`-array, één regel wijzigen mag de rest niet raken).

De echte toets is niet automatiseerbaar: over twee weken tellen hoeveel mensen iets vonden van de homelab-spec.
