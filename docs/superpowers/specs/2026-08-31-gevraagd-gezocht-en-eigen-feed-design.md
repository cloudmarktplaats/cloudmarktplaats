# Gevraagd/gezocht en de eigen feed

Ontwerp van 31-08-2026. Status: vastgesteld, nog niet gebouwd.

## 1. Aanleiding

Wouter van Os vroeg onder een LinkedIn-post om een gevraagd/gezocht-functie.
Daarop is een poll gezet met 2 richtingen: een gezocht-advertentie plaatsen, of
een mailtje krijgen zodra iemand het plaatst. In diezelfde ronde kwam er meer
feedback binnen die dit ontwerp raakt:

- **Ramon Fincken**: foto's zijn in de wizard niet te ordenen of te verwijderen.
  Een verkeerd gezette foto dwingt je een nieuwe advertentie te maken.
- **Wesley Schrik**: allebei bouwen, maar gezocht eerst. Zijn argument: wie een
  ProLiant ML380 G5 zoekt kan in deze niche eindeloos op een alert wachten,
  terwijl een gezocht-post iemands zolder opent.
- **Jefta Katuin**: laat een gezocht-post ook een melding naar aanbieders sturen
  als hij matcht.
- **Ronnie Bakker**: meldde dat de poll 2 identieke antwoorden bevat, en gaf
  losse marketingtips.

### De poll is een signaal, geen mandaat

Bij het vaststellen van dit ontwerp had de poll 15 stemmen op 776 impressies,
een respons van 2 procent, met nog 5 dagen te gaan. Twee deelnemers merkten
onafhankelijk van elkaar op dat "Mailtje als het er komt" er 2 keer in staat.
Dat is geen schoonheidsfout maar een meetfout: de alert-stemmen verdelen zich
over 2 regels terwijl de gezocht-advertentie er 1 heeft, waardoor die laatste
vanzelf als winnaar leest. **Lees de uitslag als de som van beide mailtje-regels.**

In de post staat publiek dat er nog geen regel code onder ligt en dat eerst de
goede van de 2 gebouwd wordt. Een ontwerp is geen code, dus doorontwerpen mag,
maar er wordt niets over gezocht gedeployd voordat de poll gesloten is. Dat is
dezelfde regel als in `GOVERNANCE.md`: een consultatie zonder gevolg is erger
dan niet vragen.

## 2. Wat de meting zegt

Alle cijfers hieronder zijn op 31-08-2026 met SQL op productie gemeten. Er zit
bewust geen analytics op het platform, dus dit is de enige bron.

### Leden

| Gedrag | Aantal | Aandeel |
| --- | --- | --- |
| Nooit teruggekomen | 136 | 46% |
| Alleen op de dag van registratie | 109 | 37% |
| Op een latere dag terug | 51 | 17% |
| Totaal | 296 | |

Twee onafhankelijke kolommen wijzen dezelfde 136 leden aan: `login_count = 0`
en `last_login_at IS NULL`. Die 46 procent staat dus langs 2 wegen vast.

De groei per week sinds de start: 2, 9, 173, 47, 26, 11, 4, 21, 3. Week 29 is de
lancering, week 34 is 1 losse post. De organische basis is ongeveer 3 leden per
week.

### Aanbod

262 van de 296 leden hebben nog nooit iets geplaatst. Van de 34 die dat wel
deden, plaatsten er 12 een tweede advertentie. Verkopers die de wizard doorkomen
gedragen zich dus prima, en dat maakt dit geen verkopersprobleem maar een
eerste-bezoekprobleem.

### Dichtheid

De categorieboom telt 70 categorieën, waarvan 12 hoofdcategorieën. Daarin staan
52 gepubliceerde advertenties, oftewel 0,74 per categorie.

| Hoofdcategorie | Advertenties | Verkopers | Met contact |
| --- | --- | --- | --- |
| compute | 22 | 12 | 4 |
| networking | 10 | 10 | 3 |
| servers | 7 | 3 | 1 |
| av | 7 | 2 | 2 |
| power, misc, fabrication, kabels | 11 | 8 | 2 |
| storage, books, licenses, meet | 0 | 0 | 0 |

**40 van de 52 advertenties (77%) kregen nooit 1 contact.**

### De diagnose die daaruit volgt

Als driekwart van het aanbod nooit wordt aangeraakt, is aanbod niet de schaarse
kant. **Vraag is dat.** En de werving loopt via LinkedIn, waar volgens
`AGENTS.md` juist de aanbodkant zit. Er is 2 maanden bijgevuld aan de kant die
al over was.

Daaronder ligt de echte oorzaak van die 46 procent. Wie op Routers klikt ziet 1
advertentie, op UPS 1, op Storage niets. Er is geen enkele plek op de site waar
het niet leeg is. De uitval is dus geen zelfstandig retentieprobleem maar het
gevolg van lege schappen, en geen enkele feature repareert dat op zichzelf.

Ronnie Bakker beschreef precies dit gedrag: aangemeld, rondgekeken, en met "ik
kom terug wanneer het volwassener is" weer weg.

### Grenzen van dit bewijs

Met n=52 over 2 maanden zijn de percentages ruis. De richting is hard, de
decimalen niet. `storage = 0` kan ook een indelingsartefact zijn, van verkopers
die een schijf onder Serveronderdelen hingen. Dat is 1 steekproef van 5
advertenties om te controleren voordat er conclusies aan hangen.

## 3. Objective en Key Results

Het vakgebied kent hier geen peer-reviewed antwoord op. Wat er wel is, is een
set operatorframeworks die over veel marktplaatscasussen heen hetzelfde zegt:
begin in het kleinste netwerk waar het product echt nut heeft (Andrew Chen,
*The Cold Start Problem*), versmal tot er liquiditeit ontstaat (Gurley, Tavel),
stuur op liquiditeit en niet op ledenaantal (Sarah Tavel, *Hierarchy of
Marketplaces*), en adverteer alleen aan de schaarse kant. Behandel dat als
vakkennis met patroonherkenning eronder, niet als meting.

Een eerdere versie van deze OKR stuurde op terugkeerpercentage en ledenaantal.
Die zijn verworpen: het zijn achterlopende indicatoren zonder directe knop. Je
kunt niet aan retentie werken, je kunt alleen zorgen dat iemand iets vindt. Wie
retentie als KR neemt, stuurt op de thermometer en eindigt bij herinneringsmails.

**Objective: in 1 hoek van Cloudmarktplaats is het niet leeg, en daar werkt de
marktplaats echt.**

De gekozen hoek is **networking**. Nu 10 advertenties door 10 verschillende
verkopers, dus breed gedragen in plaats van 1 opruimende zolder. Marktplaats is
slecht in enterprise-netwerkspul, dus er is geen frontale concurrentie.
Gezocht-posts zijn er scherp: dat ene SFP-model, die ene PSU. En het is het
verhaal dat de aanleiding vormde: een switch in een doos onder de trap.

| KR | Nu | Eind december 2026 |
| --- | --- | --- |
| KR1 dichtheid in networking | 10 advertenties | 40 gelijktijdig gepubliceerd |
| KR2 de vraagkant bestaat | 0 | 25 gezocht-posts, waarvan de helft binnen 14 dagen een reactie |
| KR3 liquiditeit in networking | 30% krijgt contact | 60% binnen 30 dagen |
| KR4 echte handel | 2 transacties | 15 |
| KR5 leden | 296 | 550, als afgeleide en niet als stuurknop |

KR5 is bewust een poort en geen race. 1000 leden is een doel voor 2027, met
bereik dat blijft plakken. Wordt er nu bereik ingekocht op een terugkeer van 17
procent, dan koop je vooral dode accounts van aandacht die je maar 1 keer kunt
uitgeven.

### De voorspelling die fout kan gaan

Als KR1 tot en met KR3 landen, loopt de terugkeer vanzelf van 17 procent naar
boven de 30. Gebeurt dat niet, dan klopt de diagnose in paragraaf 2 niet en
moeten we opnieuw kijken in plaats van harder duwen. Dit staat hier zodat het
achteraf niet weggeredeneerd kan worden.

## 4. Bouwvolgorde

### Stap 1. Foto's ordenen en verwijderen

Mag meteen, ook terwijl de poll loopt, want dit is niet waar de poll over ging.

`Wizard.php` kent alleen toevoegen: `$position = (int) $listing->photos()->max('position')`
gevolgd door `$position++`. Er is geen verwijder- of verplaatsmethode. Ramons
melding klopt dus letterlijk.

Toe te voegen: een foto verwijderen en de volgorde wijzigen, met hernummering
van `position` zodat er geen gaten ontstaan. Verwijderen wist ook het bestand,
en dat gaat via `PhotoFileEraser`, die per map wist. **Nooit een bestandsnaam
samenstellen uit de `mime`-kolom**, want die klopt op de oudste rijen niet met
wat er op schijf staat.

Dient KR1: dit is een muur in het enige pad dat aanbod oplevert.

### Stap 2. De gezocht-post en de feed

Pas na sluiting van de poll.

Dit is 1 stap en geen 2, omdat al vaststond dat gezocht gemengd in dezelfde
stroom landt. De feed is de container die er toch al kwam.

### Stap 3. De netwerkhoek vullen

Geen code. Van 10 naar 40 advertenties haal je niet met een feature. Dat zijn de
10 netwerkverkopers persoonlijk benaderen, en posten waar de vraagkant zit
(Tweakers, Reddit, Discord) in plaats van waar de aanbodkant zit.

Dit staat expliciet in de volgorde omdat het de zwaarste KR is en het enige
onderdeel waar geen regel code aan helpt. **Als stap 2 dit werk verdringt, heeft
stap 2 netto schade gedaan.**

### Stap 4. Het mailtje, in 2 richtingen

Wesley's volgorde, met Jefta's uitbreiding.

- Richting 1, wat de poll aanbood: mail mij als iemand X plaatst.
- Richting 2, die pas kan bestaan zodra gezocht er is: mail de verkoper als
  iemand iets zoekt in een categorie waarin hij eerder verkocht.

Richting 2 is de goedkoopste terugkeerreden voor de 34 leden die al iets
plaatsten. Ontwerp volgt in een eigen spec.

### Stap 5. De dagelijkse check leert de nieuwe getallen

`platform:daily-check` krijgt de dichtheid in networking en het aantal
gezocht-posts zonder reactie.

Let op de les die al in `AGENTS.md` staat: onbeantwoorde gezocht-posts zijn een
**voorraad**, geen aanwas. Zonder afhandelmarkering staat dat signaal binnen 2
weken elke ochtend op dezelfde rijen te roepen, precies zoals
`concepten_zonder_foto` deed. Het signaal alarmeert dus alleen op posts waarvan
de eigenaar nog nergens iets over gehoord heeft; het getal blijft het volledige
totaal.

## 5. Ontwerp van de gezocht-post

### Eigen tabel, geen `kind`-kolom

`wanted_posts` wordt een eigen tabel met een eigen model, niet een `kind`-kolom
op `listings`.

Reden: `listings` sleept een state machine, moderatie, foto's, deals,
transacties, sitemap en karma mee. Een `kind`-kolom betekent dat élke bestaande
query voortaan `where kind = 'offer'` moet dragen, en de eerste die dat vergeet
laat een gezocht-post opduiken in de dealsflow of in de sitemap. Dat is exact
het patroon van de `saveDraft`-bug van 22-08: 1 plek die buiten de regel om
ging, en 3 rijen die stilletjes verkeerd stonden.

Hergebruikt worden: de categorieboom, de contact-relay, de meldknop en de
kaartcomponent.

De prijs van deze keuze is dat de gemengde feed een union op 1 plek wordt. Dat
is zichtbaar en te testen, in plaats van een impliciete voorwaarde in twintig
queries.

### Velden

| Kolom | Type | Toelichting |
| --- | --- | --- |
| `ulid` | string, uniek | zelfde vorm als `listings`, Crockford |
| `user_id` | FK, ON DELETE CASCADE | zie erasure hieronder |
| `category_id` | FK | verplicht, anders is matchen onmogelijk |
| `title` | string | verplicht |
| `slug` | string | afgeleid van de titel, voor de detail-URL |
| `description` | text, max 500 tekens | optioneel |
| `budget_cents` | integer, nullable | optioneel, getoond als "max € x" |
| `region_postcode` | string, nullable | optioneel |
| `state` | enum | `open`, `answered`, `closed`, `expired` |
| `published_at` | timestamp | |
| `expires_at` | timestamp | `published_at` plus 30 dagen |
| `closed_at` | timestamp, nullable | |
| `first_response_at` | timestamp, nullable | voor KR2 en voor het signaal |
| `notified_at` | timestamp, nullable | afhandelmarkering, zie stap 5 |

Geen foto's, geen staat, geen aantallen, geen prijs. Het formulier is 1 scherm:
titel, categorie, budget, regio, toelichting.

De drempel is hier het hele punt. Vraag je hetzelfde als bij aanbieden, dan
plaatst niemand een oproep voor een caddy van 15 euro. En dit is de enige actie
die de 262 leden die nog nooit iets plaatsten kunnen doen zonder iets te
bezitten.

### Levenscyclus

`open` is de normale toestand. `answered` wordt gezet bij het eerste contact.
`closed` zet de eigenaar zelf. `expired` volgt automatisch 30 dagen na
publicatie.

**Verlopen is geen detail maar een ontwerpeis.** Een oproep uit juli die niemand
beantwoordde maakt de stilte zichtbaarder dan hij was. Dat is de prijs van
levendigheid als doel, en die betaal je in het ontwerp of anders later.
Verlopen posts verdwijnen uit de feed en uit de index, en blijven bereikbaar op
hun eigen URL.

### Contact

De bestaande relay wordt hergebruikt, maar in de andere richting: hier neemt een
aanbieder contact op met de zoeker.

`contact_relay_logs` is nu strikt `listing_id` plus `created_at`, met in het
model de instructie dat er nooit een e-mailadres, berichttekst of IP bij mag.
Die regel blijft ongewijzigd. De tabel krijgt een nullable `wanted_post_id`
naast `listing_id`, met een CHECK-constraint dat er precies 1 van de 2 gevuld
is. Zo blijft er 1 plek voor rate limiting en misbruikmeting.

### Moderatie en melden

Dezelfde behandeling als advertenties. `features.moderation` staat uit, dus een
gezocht-post is meteen zichtbaar. Melden, afwijzen, offline halen en bannen
blijven bestaan. Een gezocht-post met een gevulde `moderation_notes` publiceert
nooit vanzelf.

### Erasure

`AccountRemovalService` roept `$user->forceDelete()` aan en leunt op de
ON DELETE CASCADEs. `wanted_posts.user_id` krijgt daarom die cascade, en er komt
een test die afdwingt dat gezocht-posts bij accountverwijdering echt weg zijn.
Zonder die test is de belofte in het privacybeleid weer een belofte zonder code
eronder, en dat is precies de fout die op 21-08 een lid kostte.

## 6. Ontwerp van de feed

### Wat erin komt

Nieuwe advertenties, nieuwe gezocht-posts, en verkocht-kaarten.

Die laatste zijn het sterkste sociale bewijs dat er is: ze laten zien dat er echt
gehandeld wordt, en het is de enige contentsoort die vanzelf meegroeit als het
platform werkt. Prijs: 2 transacties worden er pijnlijk zichtbaar door. Dat is
acceptabel, want het is waar.

Een verkocht-kaart toont alleen wat al openbaar is: de advertentie en het feit
dat hij verkocht is. Geen koper, geen exacte datum.

Homelabposts blijven erbuiten. Anders verdrinkt de marktplaats in het sociale
deel en bouw je per ongeluk een forum.

### Een feed met een einde

Het platform produceerde in augustus 33 advertenties, ongeveer 8 nieuwe dingen
per week. De hele voorraad is zo'n 60 kaarten, wat op een telefoon neerkomt op
een minuut of 2 vegen.

Een oneindige scroll bouwen op 8 items per week is een machine die de eigen
leegte adverteert. Daarom eindigt de feed. Je scrollt, je bent bij, en er staat
letterlijk dat je bij bent met het aantal nieuwe dingen van deze week.

Geen oneindige scroll, geen aanbevolen voor jou, geen heropgewarmde oude items
om de tijd te vullen. Dat is in code te controleren en het is het tegenovergestelde
van wat elke andere feed doet.

### Jij beheert het algoritme

Geen verborgen weging, maar een zichtbaar paneel: welke categorieën, welke
straal, welke prijsklasse, sorteren op nieuwste of dichtstbij. Opgeslagen op het
account. Met een link "waarom zie ik dit?" die de letterlijke regel toont die het
item binnenliet.

Dat is punt 5 van de waarden, geen algoritmische manipulatie, niet als belofte
maar als scherm. Het filter Alles, Aanbod, Gezocht is de eerste versie van dat
paneel.

De grens van 2 advertenties per verkoper op de voorpagina
(`RecentListings::$maxPerSeller`) blijft gelden en gaat ook voor gezocht-posts
gelden. Geld mag identiteit kopen, nooit positie.

## 7. Wat er bewust niet in zit

- Geen matching-engine. Stap 4 is een mailtje op categorie, geen scoring.
- Geen reputatie, geen keurmerk. Een keurmerk botst met de bestaande lijn dat
  het zakelijke label een feitelijke mededeling is en geen keuring.
- Geen messaging. Dat blijft sub-project 2.
- Geen naamswijziging van het platform, en geen aanpassing van de eerlijke toon
  in homelabposts. Eerlijk zijn over wat een machine niet kan is deel van de
  propositie, niet iets om weg te poetsen omdat het professioneler klinkt.
- Geen oneindige scroll, zie hierboven.

## 8. Risico's

- **De feed verdringt het dichtheidswerk.** Grootste risico. Een gepolijste feed
  over 52 items is een gepolijste lege schappenwand. Stap 3 is de bottleneck.
- **Gezocht-posts blijven onbeantwoord.** Dan maakt de feature de stilte
  zichtbaarder. Het verval na 30 dagen dempt dit, en KR2 meet het expliciet: niet
  het aantal posts telt, maar het aandeel dat een reactie krijgt.
- **De poll bevestigt de keuze die we toch al maken.** Daarom staat de meetfout
  in paragraaf 1 opgeschreven, en wordt de uitslag als som gelezen.
- **Networking blijkt de verkeerde hoek.** Meetbaar aan KR1: staat de dichtheid
  in december onder de 25, dan lag het niet aan de uitvoering maar aan de keuze.

## 9. Bronnen van de cijfers

Alle getallen in paragraaf 2 komen uit SQL op de productiedatabase van
31-08-2026, uitgevoerd volgens de methode in `AGENTS.md`. De feedback in
paragraaf 1 komt uit LinkedIn-reacties en directe berichten van dezelfde week.
