# Zakelijke verkopers: label, plichten en ToS

**Datum:** 2026-08-19
**Status:** ontwerp — wacht op akkoord

## Probleem

Foundation gaat ervan uit dat er particulieren onderling handelen. De ToS zegt dat we
"bemiddelaar, geen partij bij de koop" zijn, en dat klopt — maar dat lost niets op zodra
een van de twee partijen een **bedrijf** is.

Dat moment komt eraan. In de reacties op LinkedIn meldden zich meerdere IT-bedrijven met
uitgefaseerde klanthardware: te weinig voor een opkoper, te veel voor Marktplaats.
Dat is een aantrekkelijke aanbodkant — één MSP levert meer advertenties dan het platform
er vandaag in totaal heeft. Maar zodra een bedrijf aan een consument verkoopt, gelden
er dwingende regels die geen van beide partijen kan wegcontracteren:

- **Wettelijke conformiteit** (BW 7:17). De koper mag verwachten wat hij redelijkerwijs
  mag verwachten. Bij tweedehands hardware is dat minder dan bij nieuw, maar niet niets,
  en de verkoper kan het niet uitsluiten richting een consument.
- **Herroepingsrecht bij verkoop op afstand** (BW 6:230o). Wordt er verstuurd in plaats
  van opgehaald, dan heeft de consument 14 dagen bedenktijd. Ophalen en ter plekke
  betalen is géén koop op afstand — dat scheelt, en het is precies wat hier vaak gebeurt.
- **Informatieplichten** (BW 6:230m): identiteit van de verkoper, totaalprijs, en de
  voorwaarden voor levering en herroeping.

Wie dit niet regelt, krijgt niet meteen een probleem — maar de eerste keer dat het misgaat,
gaat het mis bij de partij die er het minst op gerekend had: de MSP die dacht dat hij even
zijn zolder opruimde, en het platform dat suggereerde dat dat vrijblijvend was.

Er is nu geen enkel onderscheid in het systeem: `users` heeft geen veld voor zakelijkheid,
en advertenties tonen niets.

## Doel

Een consument kan vóór het contact zien of hij van een bedrijf of van een particulier
koopt, en een zakelijke verkoper weet wat dat voor hem betekent — zonder dat wij partij
worden bij de koop en zonder dat we een verificatie-apparaat moeten optuigen dat we niet
kunnen waarmaken.

**Nadrukkelijk niet het doel:** garanderen dat de opgave klopt. Wij zijn geen KvK-controle.
Wat we wél doen is het *vragen*, het *tonen*, en het *handhaven op melding* — precies zoals
we de rest van de moderatie doen.

## Ontwerp

### 1. Schema

Migratie op `users`:

- `seller_type` — enum-achtige string, `NOT NULL`, default `'private'`, waarden
  `private` | `business`. Postgres check-constraint op de twee waarden.
- `business_name` — nullable string. Verplicht zodra `seller_type = 'business'`.
- `business_registration` — nullable string (KvK-nummer of buitenlands equivalent).
  Verplicht bij `business`.
- `business_vat` — nullable string (btw-id). Optioneel; sommige kleine ondernemers hebben
  er geen die ze willen tonen.

Geen apart `businesses`-tabel: één account is één verkoper. Zodra dat niet meer waar is
(meerdere medewerkers onder één bedrijf), is dat een eigen sub-project — niet nu voorzien.

**Backfill:** alle bestaande users krijgen `private`. Dat is de feitelijke situatie en het
is de veilige default: `business` opgeven is een bewuste handeling.

### 2. Waar de vraag gesteld wordt

Niet bij registratie — daar verhoogt het de drempel voor de 95% die particulier is.
Wél op **twee** momenten:

1. **In het profiel**, als een schakelaar met de drie velden eronder. Dit is de plek waar
   je het aanzet.
2. **In de wizard, stap 1**, als een enkele regel voor wie nog op `private` staat:
   *"Verkoop je dit namens een bedrijf?"* → linkt naar het profiel. Eén regel, geen
   verplicht veld, geen blokkade. Dezelfde plek als de scope-link, dezelfde logica:
   het antwoord hoort waar de twijfel ontstaat.

### 3. Wat de koper ziet

Op de advertentie en op het publieke profiel: een label **"Zakelijke verkoper"** met de
bedrijfsnaam en het KvK-nummer, plus één zin:

> Je koopt van een bedrijf. Daardoor heb je wettelijke rechten die je bij een particuliere
> verkoper niet hebt — bij verzending onder meer 14 dagen bedenktijd.

Geen badge-esthetiek, geen keurmerk-suggestie. Het is een feitelijke mededeling, niet een
kwaliteitsoordeel. **Dit is belangrijk:** een badge die eruitziet als een keurmerk wekt
vertrouwen dat wij niet kunnen dragen, en dat is precies het soort belofte-zonder-dekking
waar dit platform tegen gebouwd is.

Bij `private` tonen we niets. Afwezigheid van het label is geen claim.

### 4. Wat de verkoper te zien krijgt

Bij het aanzetten van `business`, éénmalig en zonder juridisch jargon:

> Als bedrijf verkoop je onder andere regels dan een particulier. De koper heeft recht op
> wat hij redelijkerwijs mag verwachten — bij tweedehands is dat minder dan bij nieuw,
> maar niet niets, en je kunt het niet uitsluiten. Verstuur je in plaats van laten ophalen,
> dan heeft een consument 14 dagen bedenktijd. Ophalen en ter plekke afrekenen valt daar
> niet onder.
>
> Wij controleren dit niet en zijn geen partij bij de koop. Je bent zelf verantwoordelijk
> dat je opgave klopt.

### 5. Wat er in de ToS moet

Een nieuw artikel, plus een aanvulling op het bestaande bemiddelaarsartikel:

- **Opgaveplicht.** Wie bedrijfsmatig handelt, geeft dat op. Bedrijfsmatig handelen zonder
  opgave is een schending van de voorwaarden en grond voor verwijdering.
- **Eigen verantwoordelijkheid.** De zakelijke verkoper voldoet zelf aan zijn wettelijke
  informatie-, conformiteits- en herroepingsplichten. Wij bieden de vermelding, niet de
  naleving.
- **Geen verificatie.** Wij toetsen KvK-nummer en bedrijfsnaam niet inhoudelijk. We
  handhaven op melding, zoals bij de rest van de moderatie.
- **Aanvulling op het bemiddelaarsartikel.** Expliciet maken dat onze positie niet verandert
  als een van de partijen een bedrijf is: de koop komt tot stand tussen koper en verkoper.

**Kosten van deze wijziging, eerlijk benoemd:** een nieuwe ToS-versie triggert
`LegalAcceptanceMiddleware` en dus een re-acceptatieprompt voor alle 273 leden. Dat is een
onderbreking voor iedereen. **Batch deze wijziging** daarom met andere openstaande
ToS-aanpassingen in één versie, en plan hem vóór de MSP-werving — niet erna.

### 6. Wat dit níet regelt

- **DAC7 verandert niet.** Er lopen geen betalingen over het platform, dus er is niets te
  rapporteren. Zie `docs/dac7-position.md`. Zakelijke verkopers veranderen daar niets aan,
  ook niet boven de drempels — de drempel gaat over platformtransacties, en die zijn er niet.
- **Btw-vermelding.** Prijzen zijn nu één veld zonder btw-aanduiding. Voor een zakelijke
  verkoper hoort bij een consument een prijs inclusief btw te staan. *Openstaand: een
  `price_includes_vat`-vlag, of de eenvoudiger regel "prijzen zijn altijd de prijs die de
  koper betaalt".* De tweede is minder werk en beter voor de koper. Voorkeur: de tweede,
  vastleggen in de scopelijst en de wizard.
- **Marge- versus btw-factuur.** Buiten scope; dat is de administratie van de verkoper.

## Volgorde

1. Migratie + profielvelden + wizardregel (klein, geen ToS-afhankelijkheid).
2. Label op advertentie en profiel.
3. ToS-artikel — gebundeld met andere wijzigingen, één re-acceptatie.
4. Pas daarna de MSP-werving (`launch/social-strategie-2026-08.md`, post 7 op 10-09).

Stap 4 vóór stap 3 doen betekent bedrijven uitnodigen op een platform dat hun positie nog
niet beschrijft. Dat is precies de belofte-zonder-dekking die we bij anderen aanwijzen.

## Openstaande beslissingen

- **Btw-weergave:** vlag of vaste regel? (voorkeur: vaste regel, "de prijs is wat de koper betaalt")
- **Mag een zakelijke verkoper de contact-relay gebruiken,** of moet een bedrijf een
  herleidbaar adres tonen? De informatieplicht duwt richting het tweede; de privacy-
  architectuur richting het eerste. Voorstel: relay blijft, maar bedrijfsnaam + KvK zijn
  publiek — dat is de identiteit die de wet vraagt, en het adres hoeft daar niet bij.
- **Aparte filterknop "alleen particulier / alleen zakelijk"** in het aanbod? Simpel te
  bouwen, maar het splitst de markt visueel. Voorstel: niet nu.
