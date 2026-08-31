# Mailinglijst met aantoonbare toestemming

Ontwerp van 31-08-2026. Status: vastgesteld, nog niet gebouwd.

## 1. Wat dit is

Een lijst waarop iemand zich kan zetten om mail te krijgen, met of zonder
account. Twee doelen, apart aan te vinken en apart weer af te zetten:

1. **Nieuw aanbod in mijn categorieën**, per hoofdcategorie
2. **Updates over het platform**, de nieuwsbrief

De eis die eroverheen ligt is die van Nick zelf: het moet zo staan dat niemand
er iets van kan zeggen. Dat betekent niet "een vinkje erbij", het betekent dat
de toestemming aantoonbaar is, dat afmelden echt makkelijk is, en dat de
terughoudendheid in code staat en niet in een belofte.

### De relatie met de OKR en met de poll

Dit dient KR5 en de terugkeervoorspelling uit
`2026-08-31-gevraagd-gezocht-en-eigen-feed-design.md`.

Let op de botsing die daar in staat: "nieuw aanbod in mijn categorieën" **is**
de zoekalert waar de LinkedIn-poll over gaat, en die poll loopt op het moment
van schrijven nog 5 dagen. Er staat publiek dat eerst de goede van de 2 gebouwd
wordt.

Daarom deze volgorde, die op 31-08 is vastgesteld:

- Het fundament wordt nu gebouwd: toestemming, aanmelden, bevestigen, afmelden,
  segmentatie, juridische teksten. Dat is neutraal werk dat je nodig hebt voor
  élke mail die dit platform ooit verstuurt.
- **Het aanmeldformulier gaat pas publiek nadat de poll gesloten is**, want de
  tekst naast het vinkje belooft een mail die dan pas bestaat.

## 2. Wat de wet eist, en waar de code dat waarmaakt

| Eis | Waar |
| --- | --- |
| Toestemming is vrij, specifiek, geïnformeerd en ondubbelzinnig (art. 4 lid 11 en art. 7 AVG) | 2 losse, niet voorgevinkte hokjes; aanmelden is nooit voorwaarde voor iets anders |
| Toestemming is aantoonbaar (art. 7 lid 1 AVG) | `consent_text`, `consent_given_at`, `confirmed_at`, `consent_source` op de rij |
| Elk bericht bevat een makkelijke, gratis afmeldmogelijkheid (art. 11.7 lid 4 Telecommunicatiewet) | afmeldlink met token in elke mail, plus `List-Unsubscribe` |
| De afzender is herkenbaar (art. 11.7 lid 4 Tw) | vaste afzender, `replyTo` op `info@`, adres en KvK in de voettekst |
| Doelbinding (art. 5 lid 1 sub b AVG) | de 28 rijen in `waitlist_entries` gaan **niet** mee; die zijn voor 1 ander doel verzameld |
| Het doel staat in de privacyverklaring | nieuwe regel in de doelentabel, grondslag toestemming |
| Recht op verwijdering (art. 17 AVG) | accountverwijdering haalt de inschrijving weg; afmelden kan zonder account |

Dubbele opt-in is in Nederland niet wettelijk verplicht. Hij zit er wel in, om
precies de reden waar Nick om vroeg: het is het enige praktische bewijs dat de
persoon achter een adres zich zelf heeft aangemeld, en het beschermt tegen
iemand die het adres van een ander invult.

### Toestemming zonder IP-adres

Het gebruikelijke bewijs is IP plus tijdstip. Dat kan hier niet: `IpStripperJob`
wist IP's na 24 uur en dat is een architectuurbelofte van dit platform, geen
instelling. Het bewijs bestaat daarom uit het tijdstip, de bron, de letterlijke
zin waarop iemand ja zei, en het tijdstip van de bevestigingsklik uit de eigen
mailbox. Dat laatste is sterker bewijs dan een IP-adres dat je toch niet mag
bewaren.

Bewaar de **letterlijke tekst**, niet een versienummer dat naar een tekst wijst.
Verandert de formulering later, dan blijft oud bewijs anders onleesbaar.

## 3. Datamodel

Eén tabel, `mail_subscriptions`, gesleuteld op e-mailadres.

| Kolom | Type | Toelichting |
| --- | --- | --- |
| `email` | string, uniek, lowercase | de sleutel |
| `user_id` | FK nullable, ON DELETE CASCADE | leeg is "geen account"; dit ís de segmentatie |
| `wants_offers` | boolean | nieuw aanbod |
| `wants_updates` | boolean | nieuwsbrief |
| `categories` | jsonb | lijst van ltree-hoofdlabels, alleen zinvol bij `wants_offers` |
| `confirm_token` | string nullable, uniek | dubbele opt-in |
| `confirmed_at` | timestamp nullable | leeg is nog niet bevestigd, dus krijgt niets |
| `unsubscribe_token` | string, uniek | stabiel, onraadbaar, staat in elke mail |
| `consent_text` | text | de letterlijke zin waarop ja is gezegd |
| `consent_given_at` | timestamp | |
| `consent_source` | enum | `formulier`, `profiel`, `registratie` |
| `offers_sent_at` | timestamp nullable | rem en venster voor de aanbodmail |
| `updates_sent_at` | timestamp nullable | rem voor de nieuwsbrief |

Eén rij per adres, ook als iemand later een account maakt. Bij registratie met
een adres dat al op de lijst staat, wordt `user_id` ingevuld. Zo blijft er 1
afmeldpad en 1 toestemmingsdossier per adres.

## 4. Aanmelden

Drie ingangen, allemaal met dezelfde 2 hokjes en dezelfde tekst.

- **Publiek formulier** op een eigen pagina, met een verwijzing in de voettekst.
  Geen account nodig.
- **In het profiel**, voor wie al lid is.
- **Bij registratie**, niet voorgevinkt en nooit een voorwaarde om te kunnen
  registreren (art. 7 lid 4 AVG).

Beide hokjes staan uit. Vinkt iemand er geen enkele aan, dan gebeurt er niets.

### Wanneer de bevestigingsmail wél en niet nodig is

Een adres dat via het publieke formulier binnenkomt is onbevestigd: daar gaat
een bevestigingsmail heen en `confirmed_at` blijft leeg tot de klik.
Onbevestigde rijen worden na 7 dagen opgeruimd.

Een adres van een **ingelogd lid met een geverifieerd e-mailadres** is al
bewezen van hem: dat is precies wat e-mailverificatie doet. Daar wordt
`confirmed_at` meteen gezet, met `consent_source = profiel` of `registratie`.
Een tweede bevestigingsklik zou daar niets aan bewijskracht toevoegen en alleen
afhakers opleveren.

## 5. Afmelden

Elke verzonden mail draagt:

- een afmeldlink met `unsubscribe_token`, per doel af te melden en in 1 keer
  voor alles
- de `List-Unsubscribe`-header, zodat de knop in Gmail en Thunderbird werkt

Eén klik is genoeg. Geen login, geen reden, geen formulier. De
bevestigingspagina toont een ongedaan-maken-knop voor wie zich vergiste.

Wie een account heeft, vindt dezelfde schakelaars in zijn profiel. Dat is een
extra weg, **geen vervanging**: een afmelding die een login vereist voldoet niet
aan art. 11.7 lid 4 Tw, en abonnees zonder account hebben die login niet.

### Accountverwijdering neemt de inschrijving mee

`user_id` krijgt ON DELETE CASCADE, en er komt een test die afdwingt dat de
inschrijving echt weg is na `AccountRemovalService`. Post krijgen van een
platform waar je net vertrokken bent is precies de fout die in juli een lid
kostte.

Gevolg dat je moet accepteren: wie zich eerst zonder account aanmeldde en later
een account maakte en dat weer verwijdert, is ook zijn inschrijving kwijt. Dat
is de minst verrassende uitkomst van de twee, en opnieuw aanmelden kan altijd.

## 6. Verzenden

Twee commando's, allebei met `--dry-run`.

**`mail:offers`**, wekelijks. Verstuurt per abonnee de nieuwe advertenties in de
categorieën die hij aanvinkte, sinds `offers_sent_at`. **Geen nieuws is geen
mail**: staat er niets nieuws in zijn categorieën, dan gaat er niets uit en
blijft de stempel staan. Bij 8 nieuwe advertenties per week over 12
hoofdcategorieën betekent dat voor de meeste abonnees de meeste weken geen mail,
en dat is de bedoeling.

**`mail:update`**, handmatig, met de tekst die Nick zelf schrijft. Het commando
**weigert** als de vorige update minder dan 30 dagen geleden is verstuurd. Die
grens staat in code en niet in een voornemen, want dit platform verkoopt dat
elke claim in code te controleren is.

### Geen tracking

Geen open-pixels, geen klikregistratie, geen unieke links per ontvanger. Je kunt
dan niet meten hoeveel mensen openen. Dat is consistent met de rest van het
platform, het scheelt een volledige AVG-paragraaf, en het past bij "meten zonder
analytics": wat je wilt weten is of er meer wordt teruggekomen en gehandeld, en
dat meet je met SQL op `users` en `contact_relay_logs`.

### Verzendlimiet

SMTP loopt via Hostinger, geauthenticeerd als `noreply@cloudmarktplaats.nl`.
**Zoek de verzendlimiet op vóór de eerste echte ronde** en zet de queue
navenant traag. 300 adressen in 1 keer door een SMTP-account dat daar niet op is
ingericht is een goede manier om je domein in de problemen te brengen, en dat
weegt zwaarder dan snelheid. DMARC staat op `p=quarantine`, dus SPF en DKIM
moeten kloppen voordat er bulk uit gaat.

## 7. Segmentatie

`user_id is null` versus `user_id is not null` is de hele segmentatie: geen
account tegenover wel account. Beide groepen krijgen dezelfde mail, tenzij er
een reden is die niet te verzinnen is zonder de mail te schrijven. Waar het
verschil er wel toe doet is de afsluiting: iemand zonder account krijgt een
regel over wat een account extra geeft, iemand met een account niet.

Dat verschil is de enige rechtvaardiging om de segmentatie te bouwen. Bestaat
het niet in de tekst, dan is het een kolom zonder doel.

## 8. Juridische documenten

De privacyverklaring krijgt een regel in de doelentabel:

| Doel | Grondslag |
| --- | --- |
| Nieuwsbrief en berichten over nieuw aanbod | Toestemming (art. 6 lid 1 sub a AVG) |

Plus een alinea die zegt wat er bewaard wordt als bewijs van toestemming, dat er
geen IP bij zit, en hoe je je afmeldt.

**Let op de re-acceptatie.** `LegalAcceptance` prompt op `tos` én `privacy`, per
versie. Een nieuwe privacyversie zet dus iedereen die een advertentie plaatst
opnieuw voor het acceptatiescherm. In `AGENTS.md` staat dat de ToS-tekst voor
zakelijke verkopers nog open is. **Bundel die twee in 1 versiebump**, anders
krijgen leden binnen korte tijd twee keer hetzelfde scherm voor twee losse
wijzigingen.

## 9. Toon

Nederlands, kort, tweede persoon, geen marketingtaal. Voor deze lijst geldt
bovendien: gewoon de taal van de doelgroep. Jargon hoeft niet uitgelegd, een
lezer die zich hierop abonneert weet wat een SFP-module is en hoeft niet te
horen wat een homelab is. Geen uitroeptekens, geen "spannend nieuws", geen
onderwerpsregel die iets belooft wat er niet staat.

## 10. Wat er bewust niet in zit

- Geen tracking, zie hierboven.
- Geen A/B-testen op onderwerpsregels. Dat vereist meten wie wat opent.
- Geen import van de 28 rijen uit `waitlist_entries`. Ander doel, andere
  toestemming.
- Geen externe dienst (Mailchimp en verwanten). Dat zou adressen bij een derde
  neerleggen en een verwerkersovereenkomst plus doorgifte-analyse toevoegen aan
  een platform dat zijn eigen infrastructuur draait.
- Geen aparte lijst per subcategorie. De 12 hoofdcategorieën zijn de
  granulariteit; 70 zou betekenen dat vrijwel niemand ooit een mail krijgt.

## 11. Risico's

- **De aanbodmail is leeg.** Bij de huidige dichtheid krijgt een abonnee op
  bijvoorbeeld storage nooit iets. Dat is geen fout in dit ontwerp maar hetzelfde
  dichtheidsprobleem als in de andere spec, en het bevestigt dat KR1 de
  bottleneck blijft.
- **De verzendlimiet van Hostinger is nog niet opgezocht.** Dat is het enige
  openstaande feit in dit ontwerp en het moet vóór de eerste ronde bekend zijn.
- **Bundelen met de ToS-wijziging kan wachten op iets anders.** Ligt de
  ToS-tekst voor zakelijke verkopers er niet op tijd, dan is de keuze: alleen de
  privacyversie bumpen en de re-acceptatie 2 keer laten gebeuren, of wachten.
  Dat is een bewuste afweging op dat moment, geen automatisme.
