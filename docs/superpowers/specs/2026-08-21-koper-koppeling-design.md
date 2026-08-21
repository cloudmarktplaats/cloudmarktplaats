# De koper koppelen aan een verkoop

**Datum:** 2026-08-21
**Status:** ontwerp — goedgekeurd, wacht op implementatieplan

## Probleem

Een verkoper mailde het op 21-08 zelf, na zijn eerste verkoop:

> Ik wilde even aangeven dat de optie om de koper z'n gebruikersnaam in te voeren niet
> veel nut heeft: die wordt namelijk nergens met mij gecommuniceerd dus ik zou niet weten
> welke gebruikersnaam hij heeft.

Hij heeft gelijk, en het is geen UI-slordigheid. Het is een gat tussen twee ontwerpen die
allebei op zichzelf kloppen.

De contact-relay (`app/Livewire/ContactSeller.php`) is bewust anoniem: een koper heeft
**geen account nodig** en onthult alleen een e-mailadres, en alleen door te versturen. De
relay-mail bevat de titel van de advertentie, het bericht en een Reply-To. Nergens een
gebruikersnaam — die is er vaak niet eens.

Het `#deal-panel` op de advertentiepagina vraagt vervolgens om precies dat ene gegeven dat
langs die weg structureel niet bij de verkoper terechtkomt.

Er zit een tweede gat achter. `DealService::markSold()` legt **niets** vast wanneer er geen
koper wordt opgegeven: de advertentie gaat op `sold` en de methode geeft `null` terug. Er
komt geen rij in `transactions`. Een verkoop zonder bekende koper bestaat dus niet in de
data, en het veld dat je nodig hebt om die koper te kennen is niet in te vullen. Die twee
houden elkaar in stand.

En daaronder ligt een derde: `IntegrityReport` telt `deals_bevestigd` met
`where('status', 'confirmed')`. Die waarde bestaat niet — de enum kent
`pending | completed | cancelled`. Dat cijfer staat structureel op nul en zou daar ook zijn
blijven staan als deze hele functie perfect had gewerkt.

## Doel

Een verkoper kan een verkoop melden en de koper kan die bevestigen, **ook wanneer die koper
anoniem mailde en geen account had**. Zonder dat het platform berichten of adressen gaat
bewaren, en zonder dat de verkoper iets moet weten wat hij niet kan weten.

**Nadrukkelijk niet het doel:** garanderen dat de bevestiging van de echte koper komt. Die
grens blijft bestaan en hoort in `docs/known-gaps.md`.

## Ontwerp

### 1. Twee paden

**Pad B — de claim-link. Dit is het hoofdpad en werkt altijd.**
"Markeer als verkocht" wordt één knop zonder invoerveld. Elke melding legt een
`transaction` vast, óók zonder bekende koper. De verkoper krijgt daarna een claim-link en
een kant-en-klare zin om in zijn antwoordmail te plakken. De koper klikt, logt in of
registreert, en bevestigt in één klik.

Dat het platform de koper niet zélf kan mailen is geen tekortkoming maar de architectuur:
`contact_relay_logs` bewaart bewust alleen `listing_id` + tijdstip. De verkoper is de enige
die de link kán doorgeven, en dat blijft zo.

**Pad A — de gebruikersnaam in de relay-mail. Context, geen invoer.**
Is de koper ingelogd als hij het contactformulier gebruikt, dan staat er een vinkje
*"Laat de verkoper zien dat ik @robin ben"*, standaard aangevinkt. Zijn gebruikersnaam gaat
dan mee in de relay-mail.

Eerlijk over wat dit oplevert: met pad B erbij lost pad A niets meer op qua invoer. Het is
"je weet met wie je praat, en dat is volgende keer dezelfde naam" — en het is letterlijk
wat er gevraagd werd. De standaard staat aan omdat een vinkje dat standaard uit staat in de
praktijk nooit wordt aangeraakt, en anoniem blijven is dan nog steeds één klik weg.

### 2. Schema

Geen nieuwe tabel. Drie wijzigingen op `transactions`:

| kolom | wijziging |
|---|---|
| `buyer_user_id` | wordt **nullable** — een verkoop zonder bekende koper is een geldige rij |
| `claim_token` | `string(32)`, nullable, unique |
| `claim_expires_at` | timestamp, nullable |

De check-constraint `transactions_buyer_ne_seller` blijft ongemoeid: in Postgres slaagt een
CHECK die op `NULL` uitkomt, dus een rij zonder koper botst er niet mee.

`Transaction::scopeConfirmedSaleFor()` filtert op `status = completed` plus
`whereHas('buyer')` en verandert niet. Een niet-geclaimde verkoop telt dus **niet** mee voor
het trustlevel. Dat is de bedoeling: melden is geen bewijs, bevestigen wel.

`DealService::markSold()` verliest zijn `$buyerUsername`-parameter volledig en geeft voortaan
altijd een `Transaction` terug in plaats van `?Transaction`.

### 3. De claim-link

`GET /deal/{token}`. Publiek bereikbaar, maar bevestigen én weigeren vereisen `auth` +
`verified` — dezelfde lat als bij invites, waar de reden al is opgeschreven: alleen
geverifieerde leden verdienen credits. Niet ingelogd betekent inloggen of registreren met de
token in de sessie, en daarna terug naar dezelfde pagina.

Claimen en bevestigen is **één handeling**: `buyer_user_id` invullen en `status = completed`
zetten in dezelfde DB-transactie, met `lockForUpdate()` zoals de rest van `DealService`. Een
tussenstap via `/profile/deals` is friction zonder doel.

Grenzen:

- eenmalig bruikbaar
- 30 dagen geldig
- de verkoper kan zijn eigen link niet claimen (de bestaande zelfkoop-guard, nu op de token)
- verlopen of al gebruikt geeft een uitleggende pagina, geen 404

De token wordt in **platte tekst** bewaard, niet gehasht. Reden: de verkoper moet de link
later kunnen terugvinden, en bij een hash zie je hem één keer en daarna nooit meer. Wat de
token waard is voor wie hem steelt, is precies één claim op één deal — minder dan wat er
verder in die database staat.

### 4. Wat de verkoper ziet

Het paneel blijft op `#deal-panel` staan, met het anker vanaf *Mijn advertenties* dat er op
19-08 om een goede reden is bijgekomen. Wat verandert is de conditie: nu rendert het alleen
bij `state === 'published'`, en direct na een verkoop staat de advertentie op `sold`. De
link zou dus onmiddellijk onbereikbaar zijn. Voortaan is het paneel zichtbaar **zolang er
een openstaande claim voor die advertentie is**, ongeacht de staat.

Vóór de verkoop: één knop, geen veld. Erna:

```
Verkocht?
─────────────────────────────────────────────
Verkocht. Stuur de koper deze link, dan kan
hij de deal bevestigen en telt de verkoop mee
voor je verkopersprofiel.

  cloudmarktplaats.nl/deal/x7f2a9c1…
  [ Kopieer link + tekst ]

Nog niet bevestigd. Link verloopt 20 september.
```

De gekopieerde tekst is één zin voor onder de antwoordmail:

> Bedankt voor de koop. Wil je hier even bevestigen dat het is doorgegaan? <link> — één
> klik, meer is het niet.

Is de link verlopen, dan verschijnt in hetzelfde paneel een knop **Nieuwe link** die een
nieuwe token genereert. Zonder dat zit een verkoper op dag 31 klem.

Op *Mijn advertenties* krijgt een verkochte advertentie met een openstaande claim de regel
*"koper nog niet bevestigd"* die naar het anker wijst. Anders is de link kwijt zodra je de
pagina sluit.

Bij `quantity > 1` levert elke melding een eigen claim-link op. Het paneel toont ze dus als
lijst, niet als één link — twee exemplaren verkocht aan twee mensen zijn twee deals.

### 5. Wat de koper ziet

`/deal/{token}` is een pagina die je koud binnenkomt via een link van iemand die je
misschien één keer gemaild hebt. Hij legt daarom eerst uit wat hij is:

> **Deal bevestigen**
>
> @nick geeft aan dat je *Jabra Speak spiderphone* van hem hebt gekocht voor € 45,00.
>
> Bevestigen betekent alleen dat de deal is doorgegaan. Het is geen betaling en geen
> verplichting. Voor jou verandert er niets, behalve dat @nick een bevestigde verkoop op
> zijn naam krijgt — dat is hoe hier vertrouwen wordt opgebouwd.
>
> [ Ja, dat klopt ]  [ Nee, dit klopt niet ]

"Nee" zet de transactie op `cancelled`. Zonder die knop is een claim-link een
eenrichtingsclaim en kan een verkoper er ongestraft mee strooien. Weigeren zet de
advertentie **niet** terug op `published`: of er nog iets te koop staat, bepaalt de
verkoper, niet de koper die zegt dat hij het niet was.

### 6. Wat er met /profile/deals gebeurt

Deze pagina lijst nu transacties op waar jij koper bent en de status `pending` is, met een
bevestigknop ernaast. In het nieuwe ontwerp bestaat die toestand niet meer: een transactie
krijgt zijn koper pas op het moment van bevestigen, dus een `pending`-rij heeft nooit een
`buyer_user_id`. De pagina zou permanent leeg staan — precies het soort scherm dat hier niet
hoort.

De bevestigactie verhuist naar de claim-pagina. *Mijn deals* wordt daarmee een overzicht:
**jouw bevestigde deals, gekocht en verkocht**, met datum en bedrag. Dat is de enige plek
waar een lid zijn eigen handelsverleden terugziet, en het is de plek waar het trustlevel
uitgelegd kan worden.

Bestaande `pending`-rijen mét koper (aangemaakt onder het oude gedrag) blijven werken: die
worden nog getoond met hun bevestigknop, zolang ze er zijn. Tel ze vóór de deploy op
productie — is het er nul, dan kan `Deals::confirm()` meteen weg.

### 7. De meting repareren

`IntegrityReport::build()`:

- `deals_bevestigd` telt voortaan `status = 'completed'` in plaats van het niet-bestaande
  `'confirmed'`.
- Er komt één cijfer bij: `verkopen_gemeld`, het aantal nieuwe `pending`-transacties in de
  periode.

Melden naast bevestigen is het getal waaraan je ziet of de claim-link werkt. Het bestaande
signaal over deals die te lang op bevestiging wachten wordt hierdoor pas betrouwbaar: nu
kán er nauwelijks een `pending`-rij bestaan.

Dit is dezelfde les als op 19-08, in het klein: controleer of een getal überhaupt kán
bewegen voordat je conclusies trekt over gebruikers.

### 8. Testen

Pest, feature-niveau:

- `markSold` legt altijd een transactie vast met token, zonder koper
- bij `quantity > 1` telt twee keer melden af en levert twee losse links op
- claimen vult koper in én zet `completed`, in één handeling
- de verkoper kan zijn eigen link niet claimen
- een tweede claim op dezelfde token faalt
- een verlopen token faalt, en **Nieuwe link** maakt hem weer bruikbaar
- weigeren zet `cancelled`
- uitgelogd claimen leidt naar login en komt terug op dezelfde pagina
- een niet-geclaimde verkoop telt niet mee in `confirmedSaleFor`
- *Mijn deals* toont een bevestigde deal aan zowel koper als verkoper
- relay: vinkje aan zet de gebruikersnaam in de mail, uit niet, uitgelogd geen vinkje
- `deals_bevestigd` telt een echte `completed` — zodat die typefout niet terugkomt

## Volgorde

1. Migratie + `markSold` zonder gebruikersnaam + claim-token (het datamodel eerst; hierna
   wordt élke verkoop meetbaar, ook zonder dat de rest af is).
2. `/deal/{token}` met claimen en weigeren.
3. Het verkoperspaneel: één knop, de link erna, **Nieuwe link**, de regel op *Mijn
   advertenties*.
4. *Mijn deals* omzetten naar een overzicht van bevestigde deals.
5. `IntegrityReport` repareren en `verkopen_gemeld` toevoegen.
6. Pad A: het vinkje in de relay en de gebruikersnaam in de mail.

Stap 5 mag ook eerder — hij staat los van de rest, en zonder die reparatie zie je van stap 1
t/m 3 geen effect in de dagelijkse check.

## Wat dit niet regelt

- **Wie de link krijgt, kan zich koper noemen.** Een verkoper die de link naar zijn eigen
  tweede account stuurt, kweekt trustlevel. Dat kon met het gebruikersnaamveld precies zo,
  dus het is geen nieuw gat — maar het is er wel een, en het hoort in `docs/known-gaps.md`.
  De bestaande dempers blijven: `buyer_ne_seller`, en `confirmedSaleFor` telt gebande kopers
  niet mee.
- **Geen publieke profielpagina** achter de gebruikersnaam. Die bestaat niet, en dit ontwerp
  bouwt hem niet.
- **Geen mail naar de verkoper** wanneer de koper bevestigt. Netjes zou het zijn, maar het is
  een extra Mailable en AGENTS.md is streng over wie er namens het platform mailt.
- **DAC7 verandert niet.** Er lopen nog steeds geen betalingen over het platform; meer
  vastgelegde verkopen maken daar niets anders aan. Zie `docs/dac7-position.md`.

## Bij het deployen

- `routes/web.php` krijgt `/deal/{token}` erbij, dus `route:cache` moet mee in de deploy —
  anders geeft de nieuwe route een 404.
- Er komen nieuwe Tailwind-classes bij, dus `npm run build` en `public/build` meesturen.
- Sync en `route:cache` direct achter elkaar, om het venster van 500's dicht te houden.
