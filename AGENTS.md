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
- **`docker/nginx/default.conf` wijzigen? Dan `up -d --force-recreate nginx`, niet
  `nginx -s reload`.** Die bind-mount is een enkel *bestand*, en die hangt aan het
  inode. `tar xzf` vervangt het bestand door een nieuw inode, dus de container blijft
  aan het oude hangen: de reload slaagt, `nginx -t` zegt ok, en je draait nog steeds
  de vorige config. Op 21-08 bleef de redactie van claim-tokens daardoor uit terwijl
  alles groen leek — controleer altijd ín de container:
  `docker compose -f docker-compose.prod.yml exec -T nginx grep redacted /etc/nginx/conf.d/default.conf`.
- **Chown na een sync alleen de paden die je stuurde, en nooit `bootstrap/` als
  geheel.** `bootstrap/cache` is van uid 82 (www-data); zet je die op 1000, dan
  kan artisan zijn config- en routecache niet meer schrijven en faalt de deploy
  halverwege. Zelfde geldt voor `storage/`.
- **Sync en `route:cache` horen direct achter elkaar.** Tussen het uitpakken van
  een nieuwe view die naar een nieuwe route verwijst en het herbouwen van de
  routecache zit een venster waarin bezoekers een 500 krijgen. Op 19-08 waren dat
  er vier in twee seconden — zichtbaar geworden dankzij `platform:daily-check`.
- **`storage/` is van uid 82.** Artisan lokaal draaien buiten Docker faalt op de log;
  gebruik `docker compose exec -T php-fpm php artisan ...`.

### Wacht op de eerstvolgende deploy

Omdat deployen een file-sync is, zegt git níét wat er draait. Wat hier staat
is op `main` en nog niet op productie. Neem het mee met de volgende sync en
haal het dan uit deze lijst.

*(leeg op 31-08-2026: de mailinglijst is gedeployd, inclusief de vier
migraties, `route:cache`, `db:seed --class=LegalDocumentSeeder` en de
hercreatie van nginx. Geverifieerd ín de container dat de redactieregel er
staat en dat het access-log `/nieuwsbrief/[redacted]` schrijft.
`FEATURE_MAIL_LIST` staat nog uit.)*

Kwaliteitspoorten vóór elke deploy, alle drie groen:

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
```

Faalt de EXIF-auto-oriëntatietest lokaal? Dan mist je php-fpm-image `ext-exif`:
`docker compose build php-fpm`. Productie heeft hem wel.

**Larastan leest de Laravel-11-vorm `casts(): array` niet** en ziet een
`datetime`-cast dan als `string`. Los dat op het model op met een letterlijke
`@return array{...}`-shape boven `casts()` — zie `app/Models/Transaction.php` —
niet met een omweg op de aanroepplek.

## Livewire kill-switches

Een feature-flag-controle in `mount()` is **geen kill-switch**: `mount()` draait
alleen bij de eerste page load, dus een pagina die al openstond klikt gewoon door
nadat de vlag om is. Dit gat is tijdens de koper-koppeling-reeks drie keer echt
aangetoond.

De plek is `boot()`. Livewire roept die aan bij mount én bij elke hydrate, telkens
vóór de actie — dus één regel dekt het hele component. Route-middleware helpt niet:
vervolgrequests gaan naar `/livewire/update`, niet naar de oorspronkelijke route.

**Maar alleen als het hele component van die feature is.** `Deals\Claim` en
`Profile\Deals` bestaan niet zonder de dealsfunctie, dus daar staat de check in
`boot()`. `Listings\Detail` is de publieke advertentiepagina die toevallig ook een
verkooppaneel heeft — zet je de check daar in `boot()`, dan 403't de hele pagina
zodra de vlag uit gaat. Daar hoort hij dus per muterende methode, zoals bij
`markSold()` en `newLink()`.

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

`deals_bevestigd` telde tot 21-08 `status = 'confirmed'` — een waarde die de enum
(`pending|completed|cancelled`) niet kent. Dat cijfer stond dus structureel op nul,
ongeacht wat gebruikers deden. Controleer bij een nulmeting altijd eerst of het
getal überhaupt kán bewegen.

## De dagelijkse check leest mee

`platform:daily-check` draait elke ochtend om 07:30 en mailt naar `OPS_DIGEST_TO`.
Hij telt **ook afwezigheid**: nul foto's of nul advertenties in een week is hier
een alarm, geen rustige week. Zo was de fotobug zes dagen onzichtbaar.

Het signaal voor deals die op bevestiging wachten alarmeert sinds 21-08 **niet**
meer op `silence_days` (7 dagen), maar op een verlopen claim-link
(`claim_expires_at`, geldig 30 dagen). Reden: een claim-link leeft vier keer zo
lang als `silence_days`, dus alarmeren op de oude regel liet elke normale, nog
niet geclaimde melding vanaf dag 7 dagelijks vals afgaan — in precies de mail die
dit platform als enige zichtbaarheid heeft. Legacy-rijen zonder `claim_token`
(van vóór de claim-link) hebben geen vervaldatum en vallen terug op de oude
`created_at`/`silence_days`-regel.

**Onbeantwoorde GitHub-issues komen sinds 31-08 ook langs.** Rob Turks melding
bleef 29 dagen liggen; meldingen op het platform komen sinds 22-08 in deze mail
terecht, issues nog niet, en dat was hetzelfde gat. `UnansweredIssues` haalt ze
ongeauthenticeerd op (de repo is publiek, 60 verzoeken per uur is ruim genoeg
voor 1 keer per dag). Drempel staat op 3 dagen, in `ops.issue_days`.

Dit is bewust **wel** een voorraadsignaal, in tegenstelling tot de regel
hieronder. Het mag hier omdat de knop bij ons ligt: antwoorden maakt het stil.
Bij `concepten_zonder_foto` lag die knop bij de verkoper, en daar ging het
daarom staan roepen. Eruit gefilterd: pull requests (het issues-endpoint geeft
die ook terug, anders alarmeert het elke week op Dependabot), issues die de
beheerder zelf opende, en issues waar hij al op gereageerd heeft. Onbereikbaar
GitHub geeft `null` en niet een lege lijst, zodat de mail "kon het niet
vaststellen" zegt in plaats van "alles in orde".

**Een voorraad is geen alarm.** `concepten_zonder_foto` telt álle concepten
zonder foto ouder dan 24 uur, en dat getal daalt alleen als de verkoper zelf
terugkomt — vijf van de tien zijn toetsenbordgeklets dat nooit afkomt. Het
alarmeerde daardoor elke ochtend op dezelfde tien rijen van juli en augustus,
terwijl de fotobug er al sinds 19-08 uit was en de tien verkopers op 22-08
gemaild waren. Erger dan ruis: een elfde geval zou "10 concept(en)" naar "11"
laten lopen en dat leest als dezelfde ochtend als gisteren. Sinds 24-08 telt het
**signaal** alleen concepten waarvan de eigenaar nog van niemand iets gehoord
heeft (`draft_reminded_at` én `photo_bug_notified_at` allebei leeg); het
**getal** blijft het volledige totaal, dus de voorraad blijft zichtbaar. Bouw je
een nieuw signaal, vraag dan eerst of het over aanwas of over voorraad gaat —
alarmeren op voorraad zonder afhandelmarkering gaat altijd vanzelf staan roepen.

Draai hem met `--show` om het rapport in de terminal te zien zonder te mailen.
Dat is ook de snelste manier om na een deploy te controleren of je niets hebt
gebroken — hij las binnen een dag vier 500's uit de log die niemand had gemeld.

---

## Geld en zichtbaarheid

Eén regel, en die is niet onderhandelbaar: **geld mag identiteit kopen, nooit
positie.** Een bedrijf mag betalen voor een eigen pagina, zijn logo, zijn verhaal.
Betalen voor een hogere plek, langer meedraaien of voorrang in de feed breekt
"geen algoritmische manipulatie" — punt 5 van de waarden — en dat is precies de
belofte waarvoor mensen hier zijn.

De grens van twee advertenties per verkoper op de voorpagina
(`RecentListings::$maxPerSeller`) is daar het technische anker van. Die staat er
bewust vóórdat er iemand betaalt. Haal hem niet weg zonder te beseffen wat je
daarmee opgeeft.

Of er überhaupt geld gevraagd wordt aan bedrijven, is op 20-08 in consultatie
gegaan — `GOVERNANCE.md` schrijft dat voor bij geldvragen. De teksten staan in
`launch/consultatie-bedrijven-en-geld.md`. **De terugkoppeling is verplicht:**
consultatie zonder gevolg is erger dan niets vragen, en dat staat in je eigen
governance.

---

## Geen moderatie vooraf meer (22-08-2026)

`features.moderation` staat **uit**. Een advertentie is meteen zichtbaar; de
wachtrij `pending_review` is een doorgeefluik van één regel in `Wizard::submit()`,
zodat de state machine de enige route naar `published` blijft en
`ListingPublished` netjes vuurt.

Aanleiding: Rob Turks advertentie stond dagen te wachten terwijl het product al
elders verkocht was. Bij het omzetten stonden er nog twee in de wachtrij — één
sinds 30 juli, **23 dagen**. De wachtrij beschermde tegen rommel die we nog nooit
gezien hadden en kostte ondertussen echte verkopers.

**Wat blijft:** melden, afwijzen, offline halen, bannen. Alleen de poort vóóraf is
weg, niet het gereedschap erna. Een advertentie met een afwijzingsgeschiedenis
(`moderation_notes` gevuld) publiceert nooit vanzelf — dat oordeel van een mens
telt zwaarder dan de vlag.

**Terugdraaien is één configregel**, `FEATURE_MODERATION=true`, geen deploy. Dat is
met opzet: dit codepad is niet verwijderd. De teksten in de wizard, de FAQ en op
/wat-mag-erop volgen de vlag, dus ze blijven kloppen als je hem omzet.

**Het vangnet is `meldingen_open` in `platform:daily-check`**, en dat is nieuw. Er
werd nergens op meldingen gealarmeerd; ze stonden alleen in Filament, waar je voor
moet inloggen. Zonder poort vooraf is een gebruikersmelding het enige wat ons nog
waarschuwt, en de dagelijkse mail is de enige zichtbaarheid die dit platform heeft.
Alarmeert vanaf de eerste melding, niet vanaf een drempel — er wordt hier zo weinig
gemeld dat elke melding er een is om vandaag te bekijken. **Haal dat signaal nooit
weg zolang de moderatievlag uit staat**; dan is "we merken het vanzelf" een lege
bewering.

## Afhankelijkheden en `composer audit`

De CI-job `security` draait `composer audit` en `npm audit`, en die poort is
scherp: hij faalt op élk advies. Dat werkte ook — hij stond vanaf 6 augustus
rood op twaalf adviezen in guzzle en commonmark — maar niemand keek, ruim twee
weken lang. Sinds 22-08 openen Dependabot-PR's (`.github/dependabot.yml`) die
updates uit zichzelf, wekelijks en gegroepeerd; beveiligingsupdates komen los.

**Drie Laravel-adviezen staan bewust genegeerd** in `config.audit.ignore` in
`composer.json`, mét reden per stuk. Ze zijn niet weggepoetst: er bestáát geen
11.x-fix. De ernstigste is een CRLF-injectie in de standaard e-mailvalidatie
(CVE-2026-48019, high), en die is alleen opgelost vanaf Laravel **12.60**. Dat
betekent dat de openstaande blootstelling gelijkstaat aan de upgrade Laravel
11 → 12, met Filament erbij. **Behandel die ignore-regels als een schuld met een
naam, niet als opgelost** — verdwijnt de reden (de upgrade landt), dan horen de
regels er in dezelfde PR uit.

## Beslissingen die vastliggen

- **Scope van wat er verkocht mag worden**: `docs/scope.md`, gepubliceerd op
  /wat-mag-erop, gelinkt vanuit de wizard en de FAQ. De categorieboom in
  `database/seeders/CategorySeeder.php` is de bron; die pagina is de leesbare versie.
  Wijzigen gaat via een openbaar issue, niet stilletjes.
- **Zakelijke verkopers**: gebouwd op 20-08 (`seller_type` op `users`, instelling op
  `/profile/verkopen`, mededeling op de advertentie). Ontwerp in
  `docs/superpowers/specs/2026-08-19-zakelijke-verkoper-design.md`. Het label is een
  feitelijke mededeling, **geen keurmerk** — het KvK-nummer wordt niet geverifieerd,
  en afwezigheid van het label is evenmin een claim.

  **De ToS-tekst is geschreven op 31-08** en zit in artikel 6 van
  `tos.nl.md` / `tos.en.md`, gebundeld met de privacywijziging in versie `1.1.0`.
  Vastgelegd: de prijs die iemand invult is het bedrag dat de koper betaalt (btw dus
  inbegrepen, geen apart vinkje), en een zakelijke verkoper toont bedrijfsnaam en
  KvK maar geen adres, want de contact-relay blijft voor iedereen gelijk. Daarmee is
  de btw-weergave uit de lijst "nog open" af.

  **Waarom "wij verifiëren niet" mag, en tot wanneer.** De DSA legt platforms waar
  consumenten met handelaren contracten sluiten een verificatieplicht op voor
  handelaarsgegevens (artikel 30). Artikel 19 DSA stelt micro- en kleine
  ondernemingen daarvan vrij, en daar valt dit platform onder. Dat is dus geen gat
  dat we openlaten maar een positie die de wet vandaag toestaat. **Hij vervalt zodra
  het platform die drempel voorbij groeit**; dan wordt artikel 6 van de ToS onwaar en
  moet er echte verificatie onder. Niet juridisch getoetst; laat het nakijken vóór de
  MSP-werving.

  **Schuld met een naam: de aankondigingstermijn van 15 dagen.** Artikel 12 van de ToS
  belooft zakelijke verkopers dat een wijziging van de voorwaarden minstens 15 dagen
  van tevoren wordt gemeld (P2B-verordening 2019/1150, artikel 3). **Die code bestaat
  niet.** Er is geen commando dat zakelijke verkopers mailt bij een versiebump. Vandaag
  is dat onschadelijk, want op 31-08 stonden alle 296 leden op `private` en is er nul
  zakelijke verkoper. **Dit moet er zijn vóór de eerste zakelijke verkoper zich
  aanmeldt**, en dus zeker vóór de MSP-werving. Een belofte in een juridisch document
  zonder code eronder is een schuld; deze staat hier zodat hij een naam heeft.
- **DAC7**: er lopen geen betalingen over het platform, dus er valt niets te
  rapporteren. Volledige analyse in `docs/dac7-position.md`. Zakelijke verkopers
  veranderen daar niets aan.
- **Aantallen** (`quantity`) gaan over identieke exemplaren. Verschillen in specs of
  staat → aparte advertenties. Die grens staat in de hint bij het veld en hoort daar.
- **Feature flags** in `config/cloudmarktplaats.php`. `features.deals` staat aan op
  productie (geen env-override).
- **De koper koppelen aan een verkoop**: "Markeer als verkocht" is één knop en legt
  altijd een `transaction` vast, ook zonder koper. De koper vult zichzelf in via
  een claim-link (`/deal/{token}`, 30 dagen, eenmalig) die de **verkoper zelf** in
  zijn antwoordmail plakt — wij kennen het adres van de koper niet en kunnen hem
  dus niet mailen. Ontwerp in
  `docs/superpowers/specs/2026-08-21-koper-koppeling-design.md`. Het oude
  gebruikersnaamveld vroeg om iets wat de verkoper structureel niet kon weten; dat
  meldde een verkoper zelf op 21-08.

## Support is één persoon, en dat moet ergens staan

Op 21-08 zegde een lid zijn account op met: geen verwijderknop, geen supportadres,
en een GitHub-melding die 29 dagen onbeantwoord bleef. Alle drie klopten. De
duurste van de drie was de derde: er stond **nergens** dat hier één onbetaalde
maintainer zit, dus hij mocht redelijkerwijs aannemen dat iemand meekeek.

Dat staat nu in `SUPPORT.md`, in de FAQ en onder het contactblok in de footer.
**Houd die drie gelijk** als de situatie verandert — een verwachting die je één
keer uitspreekt en daarna laat verlopen is erger dan geen verwachting.

Afgehandeld op 21-08: account gewist (rijen én fotobestanden geverifieerd),
bevestigingsmail door Nick zelf verstuurd, en issues #9, #10 en #11 beantwoord
en gesloten. **Niet nogmaals mailen** — zie de waarschuwing onder "Mail".

De les die breder geldt: dit platform verkoopt "elke claim is in code te
controleren". Het privacybeleid beloofde al maanden dat een advertentie blijft
"tot je hem verwijdert" én dat je recht op verwijdering hebt, terwijl beide
knoppen niet bestonden. **Als je een belofte in een juridisch document zet, zoek
dan de code die hem waarmaakt — of haal de belofte eruit.**

## Verwijderen: wat de code echt doet

- `Listing` én `User` gebruiken **SoftDeletes**. Een gewone `delete()` zet alleen
  `deleted_at`, en dan vuren de ON DELETE CASCADEs *niet*. Van buiten ziet dat er
  identiek uit — weg uit elke query — terwijl de rijen en de foto's blijven staan.
  Erasure is `forceDelete()`, en dat is precies wat de tests afdwingen.
- `PhotoFileEraser` wist per **map**, niet per samengestelde bestandsnaam. De
  extensie van `original.{ext}` komt uit de `mime`-kolom, en op de oudste
  homelab-rijen klopt die niet met de schijf (kolom `image/webp`, bestand
  `original.jpg`). Bij de eerste echte verwijdering op productie bleef daardoor de
  foto van een verwijderd lid online staan. Raad nooit een bestandsnaam.
- `archived` was tot 21-08 terminaal én onbereikbaar: nul aanroepers. Nu is het de
  knop "Offline halen", met `archived → draft` als weg terug. Terug naar `draft`
  en niet naar `published`, zodat moderatie bindend blijft.

- **Foto's ordenen en verwijderen (31-08).** De wizard kende alleen toevoegen, dus
  een verkeerd gekozen foto kostte je de hele advertentie. Ramon Fincken meldde
  dat. Nu zit het in `ListingPhotoManager`. Twee dingen die je moet weten voor je
  eraan zit:

  **`(listing_id, position)` is uniek.** Twee foto's kunnen dus niet even tegelijk
  dezelfde plek dragen, en een rechttoe rechtaan omwisseling klapt op de
  constraint. De ruil loopt daarom via positie 0: de kolom is een unsigned
  tinyint en posities beginnen bij 1, dus 0 is de enige veilige tussenstap.

  **Na verwijderen worden de gaten gesloten.** `submit()` telt de volgende upload
  verder vanaf `max(position)`, dus een gat in de nummering laat een nieuwe foto
  op de verkeerde plek landen.

  De laatste foto van een **gepubliceerde** advertentie blijft staan, met een
  melding die naar "Offline halen" wijst. Een fotoloze advertentie die live is,
  is precies de fotobug die in juli zes dagen onzichtbaar bleef. Bij een concept
  mag het wel.

- **`Wizard::saveDraft()` degradeerde stilletjes** — opgelost 22-08. De
  step-1-payload bevatte `'state' => 'draft'` en ging via `fill()->save()`, dus
  buiten de state machine om: wie een *gepubliceerde* advertentie ging bewerken
  haalde hem stil offline, en brak hij het bewerken af dan bleef dat zo. Drie
  rijen stonden zo op `draft` mét een gevulde `published_at`; die zijn
  teruggezet. Nu krijgt alleen een *nieuwe* advertentie `draft` mee, en een
  bestaande houdt zijn toestand — tenzij de moderatievlag aan staat, want dan
  hoort een bewerking wél opnieuw langs een mens.

  Let op de tweede helft: `submit()` slaat de overgang over als de advertentie al
  `published` is. Zonder die afslag krijgt de verkoper een "state"-fout op een
  bewerking die gewoon geslaagd is, want `published → pending_review` bestaat niet.

## Mail

SMTP is Hostinger, geauthenticeerd als `noreply@cloudmarktplaats.nl` — een andere
afzender wordt vermoedelijk geweigerd. Zet daarom `replyTo` op
`info@cloudmarktplaats.nl` bij alles wat een antwoord verdient.

**Nick mailt soms zelf vanaf `admin@cloudmarktplaats.nl`.** Op 19-08 kreeg een
verkoper daardoor twee bijna identieke mails. Stem af wie verstuurt vóór je iets de
deur uit doet.

**Stempelen mag de klok van de rij niet vooruitzetten.** Doe zo'n update via
`DB::table(...)`, niet via `Listing::query()->update()`: die laatste zet ook
`updated_at`. Op 23-08 vielen tien vastgelopen concepten daardoor uit de meting
`concepten_zonder_foto` — die telt alleen concepten ouder dan 24 uur — en meldde
de dagelijkse mail "Geen signalen" terwijl er niets was opgelost. Een mail
versturen is geen activiteit van de verkoper. Twee tests in
`DailyIntegrityCheckTest` houden dit vast.

**Een commando dat mensen mailt, noteert dát ook.** `listings:notify-photo-bug`
deed dat niet, en op 22-08 was daardoor niet meer vast te stellen of de lichting
van 14 juli de mail ooit gekregen had — dus ook niet of opnieuw draaien dezelfde
mensen twee keer zou lastigvallen. Sinds 22-08 stempelt hij
`photo_bug_notified_at`, ná een geslaagde verzending en nooit tijdens een
`--dry-run` (stempelen op een proefdraai slaat de echte mail voorgoed over).
Bouw je een nieuwe eenmalige mailronde, neem die markering dan meteen mee.

Terugkerende mail loopt via `listings:remind-drafts` (dagelijks 10:00, één
herinnering per concept via `draft_reminded_at`). Draai hem **altijd eerst met
`--dry-run`**: rommelconcepten zijn niet automatisch te herkennen, daar hoort een
mens naar te kijken. `--exclude=<user-id>` slaat iemand over.

## De mailinglijst

Ontwerp: `docs/superpowers/specs/2026-08-31-mailinglijst-design.md`. Wat hier
staat volgt niet uit de code.

**`confirmed_at` is het enige gezaghebbende veld.** Een rij kan tegelijk
bevestigd zijn én een levend `confirm_token` dragen: dat is een wijziging die
in `pending_changes` staat te wachten op de klik van de eigenaar. Lees
`confirm_token !== null` dus nooit als "nog niet bevestigd"; filter altijd op
`confirmed_at` (de scope `confirmed()`). Wie dat verwart, houdt mail tegen bij
iemand die er gewoon op staat, of stuurt hem juist naar iemand die nooit
klikte.

**Wie zich afmeldde, blijft afgemeld.** Een bevestigde rij met een gevulde
`unsubscribed_at` wordt door `subscribe()` niet meer aangeraakt: geen
geparkeerde wijziging, geen vers token, dus ook geen bevestigingsmail. Anders
kan een vreemde die het adres intikt ons laten mailen naar iemand van wie is
vastgelegd dat hij nee zei. Terugkomen kan langs de herstelknop of via het
profiel van een geverifieerd lid. Bouw geen pad dat daar omheen gaat.

**De rem op de nieuwsbrief staat in `mail_editions`, niet op de abonneerij.**
Eerder las hij `max(updates_sent_at)` over de abonnees, en die rijen verdwijnen
hard: `AccountRemovalService` doet `forceDelete()` en de FK cascadeert. Eén lid
dat zijn account wiste, wiste zo de stempel van de hele nieuwsbrief mee en
zette de rem weer open. `updates_sent_at` blijft "wanneer kreeg deze persoon
iets", maar is niet meer de bron van de rem. Elke volgende
verzendbeperking hoort in een eigen tabel te staan, niet in een kolom die met
een gebruiker mee kan verdwijnen.

**`offers_sent_at` is het ijkpunt van het venster, niet alleen een
ontvangstbewijs.** `mail:offers` rekent vanaf `offers_sent_at ?? created_at`.
Geeft iemand opnieuw toestemming voor aanbod (herstelknop of profiel), dan
wordt dat ijkpunt ververst, anders komt de hele achterstand in 1 mail, en dat
is een catalogus in plaats van een nieuwsbericht. Alleen bij de overgang van
uit naar aan: verversen bij elke wijziging laat een week stil verdwijnen.

**`features.mail_list` (`FEATURE_MAIL_LIST`) staat uit tot de LinkedIn-poll
over gevraagd/gezocht gesloten is.** De tekst naast het vinkje belooft mail
over nieuw aanbod in je categorieën, en dat *is* de zoekalert waar die poll
over gaat; er staat publiek dat eerst de beste van de 2 gebouwd wordt. De vlag
stopt het formulier, het profielscherm en beide verzendcommando's. Afmelden
blijft er bewust buiten: art. 11.7 lid 4 Tw geldt ook voor mail die al
verstuurd is toen de vlag nog aan stond.

**De versiebump van de privacyverklaring is bewust nog niet gedaan.** De
nieuwe tekst gaat mee als inhoud van de bestaande versie 1.0.0: de seeder
ververst de markdown en laat `published_at` staan, dus er komt geen nieuw
akkoordverzoek. Een echte bump (1.0.0 → 1.1.0, via het Filament-paneel) zet bij
elk lid wél zo'n verzoek klaar, en dat wil je 1 keer doen. Hij hoort daarom
gebundeld te worden met de nog openstaande ToS-tekst voor zakelijke verkopers.
Dat is Nicks beslissing, niet die van de volgende bouwer.

**`OAuthController` vuurt sinds deze branch een `Verified`-event** dat hij
eerder niet vuurde: hij zette `markEmailAsVerified()` en zweeg, dus de rest van
de app hoorde nooit dat het adres bewezen was. Let op wat dat betekent voor een
toekomstige listener: een listener die aanneemt "`Verified` betekent dat iemand
op een link in zijn mail klikte" heeft het bij OAuth mis. Daar komt het bewijs
van de identiteitsprovider, zonder klik van de gebruiker in onze mail.

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
