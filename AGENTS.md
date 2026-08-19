# AGENTS.md — werkgeheugen Cloudmarktplaats

Lees dit eerst. Wat hier staat is niet uit de code af te leiden.

## Wat dit is

Open-source marktplaats voor tweedehands IT- en homelab-hardware. AGPL-3.0,
publiek op github.com/cloudmarktplaats/cloudmarktplaats. Laravel 11 + Livewire 3
+ Postgres 16 + Redis, alles in Docker. Volledige beschrijving in `README.md`;
waarden in `docs/GOVERNANCE.md` en op /waarden.

De propositie is **architectuur boven beleid**: geen trackers, IP-retentie 24 uur
afgedwongen door een cronjob, EXIF gestript bij upload, geen cookiebanner omdat er
niets te vragen valt. Elke claim moet in code te controleren zijn — dat is niet
marketing maar de reden dat mensen meedoen. Bouw niets dat die belofte alleen op
papier waarmaakt.

## Deployen

**Deployen is een file-sync, geen `git pull`.** De git-repo op de server is oud en
misleidend; kijk er niet naar om te bepalen wat er draait.

```bash
tar czf - <gewijzigde bestanden> public/build \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats \
  && tar xzf - && chown -R 1000:1000 <paden> \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan migrate --force \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan config:cache \
  && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear \
  && docker compose -f docker-compose.prod.yml restart php-fpm queue-worker'"
```

Aandachtspunten die een keer misgingen:

- **Routes worden gecachet op productie** (`bootstrap/cache/routes-v7.php`). Wijzig je
  `routes/web.php`, dan moet `route:cache` mee, anders krijgt de nieuwe route een 404.
- **Nieuwe Tailwind-classes vereisen `npm run build` en het meesturen van `public/build`.**
  Die map staat in `.gitignore` en wordt dus niet via git meegenomen.
- **Nginx hoefde vroeger als laatste herstart te worden**, omdat hij het IP van php-fpm
  eeuwig vasthield. Sinds 19-08 lost `resolver 127.0.0.11` in `docker/nginx/default.conf`
  dat op: php-fpm mag verhuizen zonder dat nginx eraan te pas komt. Verwijder die
  resolver niet — hij voorkomt een storing van minuten bij elke deploy.
- **`storage/` is van uid 82.** Artisan lokaal draaien buiten Docker faalt op de log;
  gebruik `docker compose exec -T php-fpm php artisan ...`.

Kwaliteitspoorten vóór elke deploy, alle drie groen:

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
```

Faalt de EXIF-auto-oriëntatietest lokaal? Dan mist je php-fpm-image `ext-exif`:
`docker compose build php-fpm`. Productie heeft hem wel.

## Meten zonder analytics

Er zitten bewust geen trackers op, dus er is **geen funnel-data**. Elke vraag over
gedrag beantwoord je met SQL op productie, niet met een dashboard:

```bash
ssh root@192.168.178.88 'pct exec 214 -- bash -lc "cd /opt/cloudmarktplaats \
  && docker compose -f docker-compose.prod.yml exec -T postgres psql -U app -d cloudmarktplaats -f /dev/stdin"' < query.sql
```

De vier metingen die er tot nu toe toe deden:

- `listings` per `state` + `count(DISTINCT user_id)` — hoeveel verkopers echt actief zijn
- `users.login_count` — terugkeer. Let op: de kolom bestaat pas sinds 26-07 met een
  backfill van 1, dus "1" is een ondergrens. De 0-groep is wél hard: `Register` roept
  `auth()->login()` aan zonder `recordLogin()`, dus 0 = nooit teruggekomen.
- `contact_relay_logs` — of er überhaupt contact gelegd wordt
- `transactions` — of dat contact ook wordt vastgelegd

Die laatste twee naast elkaar leggen was de vondst van 19-08: 14 contacten over 10
advertenties, 0 transacties. Niet omdat mensen niet handelden, maar omdat "Markeer
als verkocht" alleen op de publieke advertentiepagina stond en niet op Mijn
advertenties. **Als een getal op 0 staat, zoek eerst de knop voordat je de motivatie
van gebruikers in twijfel trekt.**

## Beslissingen die vastliggen

- **Scope van wat er verkocht mag worden**: `docs/scope.md`, gepubliceerd op
  /wat-mag-erop, gelinkt vanuit de wizard en de FAQ. De categorieboom in
  `database/seeders/CategorySeeder.php` is de bron; die pagina is de leesbare versie.
  Wijzigen gaat via een openbaar issue, niet stilletjes.
- **Zakelijke verkopers**: ontworpen in `docs/superpowers/specs/2026-08-19-zakelijke-verkoper-design.md`,
  **nog niet gebouwd**. Nodig zodra een MSP gaat plaatsen. Let op: de ToS-wijziging
  triggert re-acceptatie voor álle leden, dus bundelen met ander ToS-werk.
- **DAC7**: er lopen geen betalingen over het platform, dus er valt niets te
  rapporteren. Volledige analyse in `docs/dac7-position.md`. Zakelijke verkopers
  veranderen daar niets aan.
- **Aantallen** (`quantity`) gaan over identieke exemplaren. Verschillen in specs of
  staat → aparte advertenties. Die grens staat in de hint bij het veld en hoort daar.
- **Feature flags** in `config/cloudmarktplaats.php`. `features.deals` staat aan op
  productie (geen env-override).

## Mail

SMTP is Hostinger, geauthenticeerd als `noreply@cloudmarktplaats.nl` — een andere
afzender wordt vermoedelijk geweigerd. Zet daarom `replyTo` op
`info@cloudmarktplaats.nl` bij alles wat een antwoord verdient.

**Nick mailt soms zelf vanaf `admin@cloudmarktplaats.nl`.** Op 19-08 kreeg een
verkoper daardoor twee bijna identieke mails. Stem af wie verstuurt vóór je iets de
deur uit doet.

Terugkerende mail loopt via `listings:remind-drafts` (dagelijks 10:00, één
herinnering per concept via `draft_reminded_at`). Draai hem **altijd eerst met
`--dry-run`**: rommelconcepten zijn niet automatisch te herkennen, daar hoort een
mens naar te kijken. `--exclude=<user-id>` slaat iemand over.

## Waar de strategie ligt

`launch/` staat in `.gitignore` en hoort daar te blijven — dat is werkmateriaal, geen
publiek document.

- `launch/doelgroep-personas.md` — zeven persona's met behoeften, angsten en
  pleziertjes, onderbouwd met echte LinkedIn-reacties en platformcijfers
- `launch/social-strategie-2026-08.md` — postplan met kant-en-klare copy, en de vijf
  regels die uit het gemeten bereik volgen
- `launch/social-media-posts.md` — archief van de lanceercampagne

Kern daaruit: posts die over de lezer gaan halen 10–25× het bereik van posts over het
platform. En de aanbodkant zit op LinkedIn, de vraagkant op Tweakers/Reddit/Discord —
alleen op LinkedIn posten levert verkopers zonder kopers.

## Toon

Nederlands, kort, tweede persoon, geen marketingtaal. Eerlijk over wat stuk is: de
`docs/known-gaps.md` en de reacties onder posts zijn de plek waar dat hoort, en die
eerlijkheid is een deel van de propositie. Claims worden onderbouwd of ze gaan eruit.
