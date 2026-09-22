# Nieuws: tracker-vrije NL-tech reader

Ontwerp van 22-09-2026. Status: vastgesteld, nog niet gebouwd.

## 1. Wat dit is

Een leesbare stroom van Nederlands tech-nieuws, binnen Cloudmarktplaats, die de
community altijd kan raadplegen. Bronnen als Tweakers en Security.NL, plus een
handvol andere, samengevoegd tot 1 omgekeerd-chronologische lijst. Een lid vinkt
aan welke bronnen het wil zien en houdt per bron bij wat het al gelezen heeft.

De vraag die dit stuurt is niet "een reader erbij", maar: wat zet de bezoeker om
in een account en houdt hem warm tussen kopen en verkopen door. Het publiek is de
laatste groep die RSS nog dagelijks gebruikt (homelabbers, tweakers, sysadmins),
privacybewust, allergisch voor muren en mail-hengelen.

### De twee motoren

- **Bereik.** De reader is publiek leesbaar. Een schone, tracker-vrije NL-tech-feed
  is deelbaar en indexeerbaar, en dat trekt de anonieme bezoeker binnen. Een
  login-muur zou dat doden.
- **Conversie.** Het account ontsluit wat deze persona echt wil: eigen bronkeuze,
  een gelezen-status die over apparaten meegaat, en "nieuw sinds je vorige bezoek".
  Dat is de reden om een account te maken, niet "meer nieuws".

Eerlijk erbij: dit is vooral een retentie- en merkmotor, geen ruw acquisitiekanaal.
Mensen komen binnen via de marktplaats en mond-tot-mond. En de reader is zelf een
bewijsstuk van de waarden van het platform: server-side ophalen, trackers strippen,
een leeslijst die geen spoor nalaat.

## 2. Waar de belofte in code staat

Dit platform wordt afgerekend op "architectuur boven beleid". Een nieuwslezer die
externe feeds toont raakt precies die belofte, dus de privacy zit in de bouw, niet
in een zin.

| Belofte | Waar |
| --- | --- |
| De bezoeker lekt geen IP of referer naar Tweakers c.s. | De server haalt de feeds op een schema op en cachet ze; de browser praat nooit met de bron |
| Geen trackers of pixels in beeld | Alleen titel, samenvatting (HTML gestript) en link worden opgeslagen; geen images, geen embeds |
| Doorklikken is een eigen keuze, niet verstopt | Artikel-links gaan naar de bron met `rel="noopener noreferrer external"`; we proxyen de klik niet en verbergen de herkomst niet |
| Geen nieuwe afhankelijkheden | Parsen met de ingebouwde SimpleXML, ophalen met Laravels `Http::`; niets toegevoegd aan `composer.json` |
| Een stille storing telt | Een dode of falende feed wordt zichtbaar gemaakt in de UI, niet stil weggelaten |

## 3. Bronnen bij de start

Geverifieerd op 22-09-2026: opgehaald, HTTP 200, geldige RSS met items. Geen
verzonnen of dode URL's.

| Bron | Feed-url | Hoek |
| --- | --- | --- |
| Tweakers | `https://tweakers.net/feeds/mixed.xml` | hardware, homelab |
| Security.NL | `https://www.security.nl/rss/headlines.xml` | security |
| Bits of Freedom | `https://www.bitsoffreedom.nl/feed/` | privacy, digitale rechten |
| Computable | `https://www.computable.nl/rss/` | enterprise-IT |
| Emerce | `https://www.emerce.nl/rss/` | online business, tech |
| Bright | `https://www.bright.nl/rss` | consumer tech |
| iCulture | `https://www.iculture.nl/feed/` | Apple |
| Androidworld | `https://www.androidworld.nl/feed/` | Android, mobile |
| NU.nl Tech | `https://www.nu.nl/rss/Tech` | algemeen tech |

Breed genoeg om "al het NL tech nieuws" te dekken; de ruis filtert de gebruiker
zelf weg door bronnen uit te vinken.

**Geparkeerd, niet vergeten:** Techzine (Cloudflare blokkeert de server-fetch met
403), Hardware.info (410, opgegaan in Tweakers), AG Connect (geen geldige feed op
de bekende paden). Techzine is de moeite waard om later terug te halen met een
aangepaste fetch-strategie; dat is buiten scope voor de eerste bouw.

## 4. Datamodel

Vier tabellen. Namen volgen de bestaande conventie (`feed_` en `user_feed_`).

- **`feed_sources`**: `id`, `name`, `slug` (uniek), `feed_url`, `homepage_url`,
  `is_active`, `sort`, `last_fetched_at` (nullable), `last_error` (nullable text),
  `last_error_at` (nullable). Geseed met de 9 bronnen uit sectie 3.
- **`feed_items`**: `id`, `feed_source_id`, `guid`, `title`, `url`, `summary`
  (nullable text, HTML gestript en ingekort), `published_at`, `fetched_at`.
  Uniek op (`feed_source_id`, `guid`) zodat een her-fetch geen dubbels maakt.
- **`user_feed_sources`**: pivot (`user_id`, `feed_source_id`). De bronnen die een
  lid heeft aangevinkt. Geen rijen voor een gebruiker betekent: toon alle actieve
  bronnen (zinnige standaard, geen lege pagina na registratie).
- **`user_feed_reads`**: watermerk (`user_id`, `feed_source_id`, `read_at`). Een
  item met `published_at > read_at` geldt als ongelezen. Geen rij = alles ongelezen.
  Dit groeit hooguit met gebruikers maal bronnen, niet met artikelen.

## 5. Ophalen en opschonen

Een console-command `news:fetch`, ingehangen in de bestaande scheduler in
`bootstrap/app.php`, elke 20 minuten.

- Loopt de actieve bronnen langs, **elke bron in een eigen try/catch**, zodat 1
  falende feed de rest niet meesleept. Ophalen met `Http::` met een korte timeout,
  een eigen user-agent, redirects volgen.
- Parsen met SimpleXML, zowel RSS (`<item>`) als Atom (`<entry>`). Per item:
  titel, link, samenvatting (`strip_tags` plus inkorten, geen images), publicatie-
  datum, en een guid (het `<guid>`/`<id>`, met de link als terugval).
- Upsert op (`feed_source_id`, `guid`). Slaagt de fetch, dan `last_fetched_at`
  bijzetten en `last_error` wissen. Faalt hij (netwerk, HTTP-fout, onparsebare XML),
  dan `last_error` plus `last_error_at` schrijven en doorgaan met de volgende bron.
- Snoeit na afloop items ouder dan 60 dagen. De tabel blijft daarmee klein
  (9 bronnen maal enkele tientallen items).
- Idempotent en zonder verrassingen, in de lijn van `platform:daily-check`.

## 6. UI en routing

### Publieke pagina `/nieuws`

Livewire-component `News\Reader`, gelinkt vanuit de hoofdnavigatie en de footer.

- **Anoniem**: samengevoegde, omgekeerd-chronologische stroom van alle actieve
  bronnen. Per item: bron-label, titel (link naar de bron), samenvatting, relatieve
  tijd. Een uitnodiging om in te loggen voor eigen bronkeuze en gelezen-status.
- **Ingelogd**: dezelfde stroom, maar beperkt tot de aangevinkte bronnen. Boven de
  lijst staan **bron-chips die je aan- en uitklikt en die direct persisten** naar
  `user_feed_sources`. Het aanvinken zit dus in de reader zelf, niet in een aparte
  profielstap. Per bron een ongelezen-teller uit het watermerk, en een knop
  "markeer gelezen" die het watermerk op nu zet (per bron, plus 1 knop voor alles).

### Zichtbare storing

Een bron waarvan de laatste fetch faalde of lang geleden is, toont subtiel
"bijgewerkt 2 u geleden" of "tijdelijk niet bereikbaar". De storing die niemand
meldt, in beeld gebracht, precies zoals de rest van het platform dat doet.

## 7. Foutafhandeling

- Fetch per bron geïsoleerd; een fout stopt die bron, niet de run.
- De reader-pagina leunt alleen op de gecachte `feed_items`, dus een trage of
  onbereikbare bron vertraagt de pagina nooit.
- Onparsebare of lege feeds leveren geen items en zetten `last_error`; de pagina
  blijft werken met wat er wel is.
- Een guid die ontbreekt valt terug op de link, zodat dedup blijft werken.

## 8. Tests (Pest)

Netwerk wordt gefaket met `Http::fake`; geen enkele test raakt het echte internet.

- **Parser**: een RSS-fixture en een Atom-fixture leveren de juiste velden; HTML in
  de samenvatting wordt gestript; een her-fetch met dezelfde guid maakt geen dubbel
  item.
- **Command**: `news:fetch` vult `feed_items`; een bron die 500 of rommel teruggeeft
  krijgt `last_error` terwijl de andere bronnen wel binnenkomen; items ouder dan 60
  dagen worden gesnoeid.
- **Reader**: anoniem toont alle actieve bronnen; ingelogd toont alleen de selectie;
  een item nieuwer dan `read_at` telt als ongelezen; "markeer gelezen" zet het
  watermerk bij en de teller op 0.
- **Chips**: een bron aan- of uitvinken persisteert in `user_feed_sources` en werkt
  de stroom bij.
- **Links**: `/nieuws` is bereikbaar en gelinkt vanuit nav en footer.

## 9. Buiten scope nu (YAGNI)

Per-artikel gelezen-status (in plaats van het watermerk), door gebruikers
toegevoegde eigen feed-URL's, een digest-mail van je bronnen, full-text of
proxy-lezen binnen de site, zoeken door het archief, en afbeeldingen bij items.
Allemaal later mogelijk op dit fundament; geen ervan is nodig om de twee motoren
uit sectie 1 te laten draaien.
