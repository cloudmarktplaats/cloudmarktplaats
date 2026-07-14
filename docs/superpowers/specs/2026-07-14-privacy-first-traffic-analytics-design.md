# Verkeer meten zonder tracker — nginx-logs zonder IP + traffic:report

**Datum:** 2026-07-14
**Status:** ontwerp goedgekeurd door Nick (chat), implementatieplan volgt

## Wat en waarom

We willen twee dingen weten: **wat levert een seller-share op** (de UTM's uit
[`2026-07-14-listing-published-share-design.md`](2026-07-14-listing-published-share-design.md))
en **wat doet de LinkedIn-funnel**. Vandaag meten we niets: `FEATURE_UMAMI=false`,
die vlag wordt nergens in `app/` of `resources/` gebruikt, er is geen tracking-script,
en `IncrementViewJob` telt views zonder bron. Alles komt binnen als `direct`.

Besluiten uit de brainstorm:

1. **Geen Umami.** Zie de blokkade hieronder — de prijs is een herziene
   privacyverklaring en heracceptatie door 67 gebruikers, daags na de lancering.
2. **De data is er al.** nginx logt de LinkedIn-referrer al
   (`"android-app://com.linkedin.android/"`) en de volledige querystring met UTM's.
   We hoeven niets te tracken; we moeten alleen lezen wat al binnenkomt.
3. **Het IP gaat eruit** (niet hashen, niet /24-maskeren). Zonder IP is er geen
   persoonsgegeven, dus geen bewaartermijn-discussie.
4. **Geen dashboard.** Een artisan-command volstaat op deze schaal (67 gebruikers).

## De blokkade: Umami breekt een belofte die op vijf plekken staat

Analytics toevoegen is hier geen technische keuze maar een juridische. De belofte staat
in een **geaccepteerd juridisch document**:

> "Geen trackingcookies, **geen analytics**, geen advertentie- of profileringsnetwerken."
> — `database/seeders/legal/privacy.nl.md:19`

En vier regels erboven: *"Waar we iets beloven, is dat ook in de code afdwingbaar
gemaakt."* Dezelfde belofte staat in `pages/home.blade.php:75` ("Geen trackers, geen
analytics. Kijk maar in de code."), `components/marketing/footer.blade.php:48`,
`pages/faq.blade.php:11`, `pages/about.blade.php:39` en zelfs
`emails/seller-contact.blade.php:35`.

Met het bestaande `LegalAcceptance`-systeem betekent een herziening dat alle 67
gebruikers opnieuw akkoord moeten geven. Dit ontwerp vermijdt dat volledig: **er
verandert niets aan de privacyverklaring** en de belofte wordt strikt genomen wáárder,
omdat we straks minder loggen dan we vandaag toestaan.

## Bestaand probleem dat dit meteen oplost

`docker inspect cloudmarktplaats-nginx-1` toont log-driver `json-file` met een **lege
config**: geen `max-size`, geen `max-file`. De oudste regel dateert van **2026-07-03** —
elf dagen aan `combined`-format access-logs mét echte IP-adressen
(`"109.37.145.207"` via `X-Forwarded-For`), ongeroteerd en groeiend.

Dat is in strijd met wat we publiceren:

> "IP-adressen worden binnen 24 uur uit ons systeem gestript (`IpStripperJob`, zie de code)."

`IpStripperJob` doet precies wat hij belooft — maar hij ruimt de **database** op en kent
de nginx-logs niet. De belofte is vandaag dus al onwaar, los van dit ontwerp. Onder de
AVG is dit een bewaartermijn-/dataminimalisatieprobleem: we verwerken persoonsgegevens
langer dan we documenteren.

## Aanpak

### 1. nginx: eigen logformaat zonder IP

In `docker/nginx/default.conf`:

```nginx
# Access logging without $remote_addr: we publish "geen trackers" and strip IPs
# within 24h, but nginx's `combined` default was logging real IPs (via
# X-Forwarded-For) into an unrotated docker json-file — 11 days' worth as of
# 2026-07-14. Dropping the IP entirely means there is no personal data to retain,
# so no retention job is needed here at all.
#
# What stays: time, request line (the querystring carries our utm_* params),
# status, referer (this is how we see LinkedIn), user agent (bot filtering).
log_format cmp_privacy '- [$time_local] "$request" $status $body_bytes_sent '
                       '"$http_referer" "$http_user_agent"';

access_log /app/storage/nginx/access.log cmp_privacy;
```

- **`error_log` blijft naar stderr**, zodat `docker compose logs nginx` bruikbaar blijft
  voor debuggen. Alleen access-logging verhuist.
- **`access_log off` op `/build` en `/fonts` blijft staan** (regel 44): statics zijn ruis.
- **Bewust niet in `storage/logs/`.** Daar staat `laravel.log`, geschreven door www-data;
  nginx' master draait als root. Gemengde eigenaars in die map is exact hoe de
  web-logging op 2026-07-03 stuk ging (zie `[[artisan-as-root-breaks-web-logging]]`).
  Vandaar `storage/nginx/`, met alleen nginx als schrijver en www-data als lezer (644).

### 2. `storage/nginx/` aanmaken en negeren

`storage/.gitignore` bevat nu `app`, `framework`, `logs`. Daar komt `nginx` bij, met een
`storage/nginx/.gitignore` (`*` / `!.gitignore`) volgens hetzelfde patroon als de rest —
zodat de map in git bestaat maar de logs niet.

**De map moet bestaan vóórdat de nginx-config live gaat, anders ligt de site eruit.**
Geverifieerd (2026-07-14, lokale nginx-container):

```
nginx: [emerg] open() "/app/storage/nginx-does-not-exist/a.log" failed (2: No such file or directory)
nginx: configuration file /etc/nginx/nginx.conf test failed
```

nginx weigert te starten bij een ontbrekende log-map — `nginx -t` faalt en een
`restart` brengt hem niet terug. `storage/nginx/` bestaat vandaag **niet**, op dev noch
prod, en `storage/` is een bind-mount (`./:/app`), dus de map moet op de **host** staan.

Verplichte deploy-volgorde:

1. `mkdir -p storage/nginx` op prod, eigendom zodat nginx' master (root) kan schrijven en
   www-data kan lezen.
2. Pas daarna `default.conf` syncen.
3. `docker compose exec nginx nginx -t` **vóór** de restart — dat is de goedkope
   controle die dit hele scenario afvangt.
4. Restart nginx.

Bij twijfel: `nginx -t` faalt luid en verandert niets; een restart met een kapotte config
is wat de site down brengt.

### 3. `php artisan traffic:report [--days=7]`

Leest `storage/nginx/access.log`, filtert bot-user-agents, en print drie tabellen:

| tabel | kolommen | beantwoordt |
|---|---|---|
| Referrers | bron, bezoeken | "wat doet mijn LinkedIn-post?" |
| UTM-bronnen | `utm_source`, `utm_campaign`, bezoeken | "wat levert een seller-share op?" |
| Top-pagina's | pad, bezoeken | "waar landen mensen?" |

Referrers worden genormaliseerd tot hun herkomst: `android-app://com.linkedin.android/`
en `https://www.linkedin.com/feed/` tellen allebei als `linkedin`. Interne referrers
(onze eigen host) tellen als `intern` en staan los van extern verkeer, anders telt elke
doorklik op de site mee als "bezoek".

Alleen `text/html`-achtige requests tellen: `/storage/...`-hits (foto's) en `/livewire/...`
zijn geen paginabezoeken. Filteren op het request-pad, niet op content-type — dat laatste
staat niet in de log.

### 4. Rotatie

Zonder IP is er geen privacy-reden om te roteren, alleen schijfruimte. Twee dingen:

- **Docker json-file krijgt `max-size: 10m`, `max-file: 2`** in `docker-compose.prod.yml`
  én `docker-compose.yml` — vangnet voor `error_log` en voor élke container, zodat dit
  niet opnieuw stilletjes groeit.
- **`storage/nginx/access.log` wordt wekelijks getruncate** via de bestaande scheduler
  (naast `IpStripperJob`). Truncate (`> file`), geen rename: nginx houdt de filehandle
  open en zou na een rename naar de oude inode blijven schrijven tot een `USR1`-signaal.

### 5. Eenmalig: de bestaande IP-logs wissen

De elf dagen aan json-file logs met IP's moeten weg. Dit is een prod-actie, geen code:
`docker compose -f docker-compose.prod.yml up -d --force-recreate nginx` na het toevoegen
van de log-opties maakt een verse log aan; de oude json-file verdwijnt met de oude
container. Verifieer daarna dat `docker inspect` de rotatie-opties toont.

## Wat dit niet oplost

**De funnel blijft een schatting.** Zonder identifier kun je bezoeken en aanmeldingen
tellen, maar niet aan elkaar koppelen. "340 bezoeken, 12 aanmeldingen" is een verhouding,
geen bewezen pad. Dat is de bewuste prijs van het IP eruit gooien.

**Geen unieke bezoekers.** 340 bezoeken kunnen 340 mensen zijn of 40 die acht keer keken.
Voor "werkt mijn post?" is de verhouding genoeg; voor cohort-analyse niet.

**Geen realtime.** Het is een command, geen dashboard. Wie een grafiek wil, draait het
command opnieuw.

## Testen

- **Unit** `TrafficReportTest`: parseert een fixture-logbestand met bekende regels →
  correcte aggregatie per referrer/UTM/pad; bots eruit; interne referrers apart;
  `/storage/`- en `/livewire/`-hits niet meegeteld; `--days` filtert op datum.
- **Regressie** `NginxLogFormatTest` (of handmatig geverifieerd in het plan): een
  gerenderde logregel bevat **geen** IP-adres. Dit is de kern van de belofte; het verdient
  een test die faalt als iemand `$remote_addr` terugzet.
- **Handmatig**: na deploy een pagina opvragen met `?utm_source=linkedin`, en bevestigen
  dat de regel in `storage/nginx/access.log` de UTM en de referrer bevat en geen IP.

## Niet in scope

- Umami of enig ander client-side script (zie de blokkade).
- Unieke bezoekers, cohorten, sessies.
- Een dashboard/UI.
- `IncrementViewJob` uitbreiden met `utm_source` (kan later; overlapt deels met dit
  ontwerp, maar meet alleen listing-views).
- De privacyverklaring wijzigen — expliciet niet nodig.
