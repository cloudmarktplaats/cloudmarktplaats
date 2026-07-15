# De founding-100 is een badge, geen poort

**Datum:** 2026-07-15
**Status:** ter review

## Waarom

De cap van 100 was schaarste-marketing: "de eerste 100" trok mensen aan. Dat heeft gewerkt — de cohort zit vol. Maar de cap is ondertussen iets anders gaan doen dan bedoeld: hij houdt aanbod tegen.

Het platform heeft 100 leden en te weinig advertenties. Elke drempel voor een nieuw lid kost aanbod, en aanbod is precies wat er ontbreekt. De wachtlijst is bovendien al feitelijk lek: er staan 19 werkende invite-codes uit die de cap volledig omzeilen. Zodra die worden ingewisseld zitten we op ~117 leden. De cap houdt dan niemand meer tegen die we willen — alleen de mensen die géén invite kennen. Dat is geen eerlijke grens, dat is een kennissenfilter.

Dus: de 100 stopt met poortwachter zijn en wordt wat hij eerlijk gezien altijd al was — een historisch feit. De eerste 100 waren er het eerst. Dat verdient een badge, permanent. Het verdient geen slagboom.

Dit vervangt het eerder verkende "slapende leden wippen"-mechanisme. Dat mechanisme bestond alleen om plekken vrij te maken onder een cap. Zonder cap is er niets vrij te maken en is het mechanisme overbodig — inclusief het meetprobleem dat het onoplosbaar maakte (een lid dat alleen kijkt is de vraagzijde, niet dood gewicht).

## Wat er verandert

### Beslissing 1 — registratie gaat open

`FEATURE_WAITLIST=false` op productie. De variabele staat nu niet in prod's `.env` en de config-default is `true`, dus hij moet expliciet worden toegevoegd; de default in `config/cloudmarktplaats.php` blijft ongemoeid.

Gevolg via `FoundingCohort::isRegistrationOpen()`: altijd `true`. Iedereen kan zich aanmelden, met of zonder invite. De wachtlijst-tak in `Register.php` wordt onbereikbaar maar blijft bestaan — de flag terugzetten sluit registratie weer, zonder code-wijziging. De 19 openstaande codes blijven werken; ze zijn alleen niet meer nodig.

### Beslissing 2 — de badge wordt gestempeld op uitgifte, niet op ledental

**Dit is de kern van de wijziging, en de reden dat dit niet één env-var is.**

`hasFoundingSpot()` telt nu actieve leden:

```php
public function members(): int
{
    return User::query()->where('is_banned', false)->count();
}

public function hasFoundingSpot(): bool
{
    return $this->members() < $this->size();
}
```

De docblock noemt bans expliciet als bedoeld gedrag: *"a banned founder frees a slot for the next arrival."* Onder een cap was dat verdedigbaar — er kwam letterlijk een plek vrij. Maar het lek zit breder dan bans, en het is **al gebeurd**.

`User` gebruikt `SoftDeletes`. `members()` draait via Eloquent, dus de global scope sluit zachtverwijderde rijen uit. Een verwijderd lid houdt zijn rij én zijn `is_founding_member = true`, maar telt niet meer mee. Meting op productie, 15-07:

```
rijen totaal: 101 | zacht verwijderd: 1 | levend: 100 | levende badges: 100
```

Wat er gebeurde: één founding member verwijderde zijn account → `members()` viel naar 99 → `hasFoundingSpot()` werd weer `true` → de eerstvolgende aanmelder kreeg badge #101. Van buiten ziet dat er gezond uit (100 leden, 100 badges), maar er zijn 101 badges gestempeld en de 101e hoorde niet bij de eerste 100. Wordt dat account ooit hersteld, dan staan er 101 levende badges.

Bans doen hetzelfde (`is_banned` wist de kolom evenmin), maar verwijdering is de route die nu al gevuurd heeft. Onder de oude cap gebeurde dat zelden. Met open registratie vuurt het bij elk vertrek.

De badge moet een historisch feit zijn, geen momentopname van het ledental. Daarom telt hij voortaan gestempelde badges — inclusief die van vertrokken leden, want die zijn er wel degelijk geweest:

```php
public function hasFoundingSpot(): bool
{
    return User::withTrashed()->where('is_founding_member', true)->count() < $this->size();
}
```

`withTrashed()` is hier het hele punt, niet een detail: zonder dat zou de teller opnieuw op vertrek reageren en is er niets opgelost.

Dit is monotoon — zodra 100 badges gestempeld zijn komt er nooit meer één bij, en vertrek of ban wekt geen plek op. De drie aanroepers (`Register.php:93`, `OAuthController.php:111`, `SiweOnboarding.php:62`) wijzigen niet; de betekenis van `hasFoundingSpot()` klopt daarna gewoon.

**Direct gevolg:** er staan nu 101 badges. De uitkomst is dus permanent `false` zodra dit live gaat — de cohort is per direct dicht en niemand krijgt nog een badge. Dat is de bedoeling. Die 101e badge trekken we niet in: die persoon heeft hem in goed vertrouwen gekregen, het was onze fout, en iemand zijn badge afpakken is precies het tegenovergestelde van "afscheid nemen met behoud van relatie". We leven met 101.

`members()` blijft tellen wat het telt (actieve leden) en blijft `spotsLeft()` voeden; alleen `hasFoundingSpot()` ontkoppelt ervan.

### Beslissing 3 — de homepage-teller

Twee dingen op de homepage gaan stuk zodra het ledental over de 100 loopt.

**De teller.** `launch-stats.blade.php:8` rendert `{{ $members }} / {{ $cohort }}`. Bij 117 leden staat er `117 / 100`. De voortgangsbalk is afgetopt (`min(100, …)`), het getal niet.

**De zin.** Regel 12 zegt bij een volle cohort: *"De eerste 100 zijn binnen. Nieuwe leden komen op de wachtlijst."* Met `FEATURE_WAITLIST=false` is dat onwaar — er is geen wachtlijst meer. Deze zin staat er nú al, want `$full` is al `true`.

Zodra de cohort dicht is verdwijnt de schaarste-ankering en toont de sectie groeicijfers.

**`$full` wordt anders bepaald.** Nu geldt `$full = $members >= $cohort` — opnieuw een momentopname van het ledental. Verwijderen twee leden zich, dan zakt `$members` naar 99 en klapt de homepage terug naar *"1 plek vrij"*, terwijl er geen badge meer te vergeven is (de badge-teller staat op 101). Dat is dezelfde verwarring tussen ledental en geschiedenis als in Beslissing 2, één laag hoger. `$full` volgt daarom de badge-toestand:

```php
'full' => ! app(FoundingCohort::class)->hasFoundingSpot(),
```

Daarmee kan de weergave nooit terugklappen naar een schaarste die niet meer bestaat, en zijn "is de cohort dicht?" en "krijgt de volgende aanmelder een badge?" per definitie hetzelfde antwoord.

- **Niet vol** (`$full === false`): ongewijzigd — `X / 100`, "plekken vrij", voortgangsbalk. Deze tak is op productie dood maar blijft correct voor verse installaties en tests.
- **Vol** (`$full === true`): het `X / 100`-blok en de voortgangsbalk verdwijnen. In plaats daarvan twee levende getallen — het ledental en het aantal openstaande uitnodigingen — met een zin die klopt: *"De eerste 100 zijn binnen — zij vormen de cultuur. Nieuwe leden zijn nog steeds welkom."*

```
BETA · DE COMMUNITY

  101  leden
   19  uitnodigingen open

De eerste 100 zijn binnen — zij vormen de
cultuur. Nieuwe leden zijn nog steeds welkom.
```

De reden: 100/100 bevroren tonen is een monument voor een deur die dicht zit. Het ledental dat groeit is een eerlijk signaal dat de club leeft, en dat dient het doel — meer aanbod, meer mensen — beter dan een schaarste die niet meer bestaat. De uitnodigingen-teller laat zien dat leden elkáár binnenhalen; dat is precies het gedrag dat we willen belonen.

Beide cijfers komen uit `StatsService::homepageStats()` (60s cache), zodat de publieke homepage goedkoop blijft:

- **Leden:** hergebruikt de bestaande `founding_members`-sleutel. Let op de bekende duplicatie — die draait dezelfde query als `FoundingCohort::members()` (`where('is_banned', false)->count()`). Die twee moeten consistent blijven; er komt geen derde teller naast.
- **Uitnodigingen open:** `InviteCode::redeemable()->count()`. Die scope bestaat al (`InviteCode.php:59`) en dekt precies de bedoeling: ongebruikt, niet ingetrokken, niet verlopen. Niet met de hand herbouwen — dan lopen de definities uiteen.

### Beslissing 4 — vertalingen

`lang/en.json` bevat de wachtlijst-strings; NL is de bron én de sleutel. De onwaar geworden zin op regel 14 vervalt. Nieuw te vertalen: de vervangende zin, `Beta · de community`, `leden` en `uitnodigingen open`.

De strings op regel 6 en 10 (wachtlijst-formulier, "beta zit vol") horen bij de nu-onbereikbare tak en blijven staan — de flag kan terug, en een ontbrekende vertaling zou dan stilletjes Nederlands tonen aan Engelse bezoekers.

Verificatie van EN gaat via `artisan tinker`, niet via curl: de locale is sessie-gebonden (`/taal/{locale}`), er is geen `/en`-URL.

## Wat er niet verandert

- `StatsService::FOUNDING_COHORT = 100` blijft 100.
- Bestaande badges blijven staan. Niemand verliest iets.
- `WaitlistResource` (Filament) en `waitlist:invite` blijven bestaan, slapend. De wachtlijst-tabel is leeg. Weghalen is verlies van een terugweg voor nul winst.
- De 19 openstaande invite-codes blijven geldig.
- Geen enkel bestaand lid wordt geraakt, verwijderd of gedegradeerd. Er wordt niemand gewipt.

## Risico's

**De schaarste is weg als wervingsargument.** Bewust. Hij had zijn werk gedaan en kostte inmiddels aanbod. Terugdraaien is één env-var — daarom blijft de wachtlijst-code staan.

**"19 uitnodigingen open" kan gelezen worden als een poort.** Het risico van deze teller: een bezoeker die net gelezen heeft dat iedereen welkom is, ziet een getal dat op restplekken lijkt en concludeert dat hij een code nodig heeft — precies de drempel die we weghalen. Daarom staat het cijfer expliciet naast het ledental en onder een zin die zegt dat nieuwe leden welkom zijn, en heet het label "uitnodigingen open" en nadrukkelijk niet "plekken vrij". Bij de implementatie is dit een copy-beslissing, geen detail. Blijkt het toch als drempel te werken, dan is het cijfer weghalen één regel.

**De uitnodigingen-teller kan naar 0 lopen.** `redeemable()` sluit verlopen codes uit, dus het getal daalt vanzelf — door inwisseling én door verval. Dat is eerlijk (het is een levend cijfer), maar "0 uitnodigingen open" is een dode indruk naast een levend ledental. Als het structureel 0 wordt, hoort de teller weg; hij verdient geen mechanisme om hem kunstmatig te vullen.

**Meer aanmeldingen betekent meer moderatie.** Registratie open zetten haalt het kennissenfilter weg dat nu de facto de rem is. Wie er binnenkomt is daarna een moderatievraag, geen registratievraag. Dit is een houding, geen mechanisme: er is geen extra spamdrempel voorzien in deze wijziging. Als dat een probleem wordt, is dat een volgende spec.

**Het gedrag "vertrek maakt een badge-plek vrij" verdwijnt.** Dat is precies het doel (Beslissing 2), maar het is een bewuste gedragswijziging: onder een teruggezette `FEATURE_WAITLIST=true` maakte een vertrekkend of geband lid vroeger ook een badge-plek vrij. Dat gebeurt niet meer. Voor *registratie* (`isRegistrationOpen()` → `members()`) blijft vertrek wél een plek vrijmaken, en dat is juist: een vertrokken lid neemt geen ledenplek in. De twee begrippen worden hier bewust uit elkaar getrokken — ledental is een momentopname, de badge is geschiedenis.

**De badge-check staat buiten zijn transactie.** `Register.php:93` berekent `$foundingMember` vóór `DB::transaction()`; twee gelijktijdige aanmeldingen bij 99 badges lezen allebei "plek vrij" en stempelen allebei. Bewust niet opgelost: na Beslissing 2 is de teller op 101 en is de uitkomst permanent `false`, dus het venster is voorgoed dicht. Een lock bouwen voor een tak die nooit meer `true` wordt is verspilling. Vermeld omdat het bij een verse installatie of een verhoogde `FOUNDING_COHORT` terugkomt — dán pas oplossen.

## Testbaarheid

- `hasFoundingSpot()` telt badges, niet leden: maak 100 gebadgede users, **verwijder** er één (soft delete — dit is de route die op productie gevuurd heeft), verwacht `false` (nu: `true`). Idem met een ban in plaats van een verwijdering.
- `isRegistrationOpen()` is `true` met `waitlist=false` bij een volle cohort — bestaat al (`FoundingCohortTest.php:92`).
- Registratie slaagt bij 100+ leden met `waitlist=false`, en lid 101 krijgt géén badge.
- De homepage rendert geen `117 / 100`: met >100 leden toont `launch-stats` het ledental en niet de cohort-breuk.
- De uitnodigingen-teller telt alleen inwisselbare codes: maak een gebruikte, een ingetrokken, een verlopen en twee open codes, verwacht `2`.
- De weergave klapt niet terug: met 100 gestempelde badges en daarna een verwijderd lid (99 leden) toont de homepage géén "plek vrij" — `$full` blijft `true`.
- EN-vertaling van de nieuwe zin bestaat en resolvet (via tinker).

## Volgorde van uitrol

Beslissing 2 (badge-telling) moet vóór Beslissing 1 (flag) live. Andersom bestaat er een venster waarin registratie openstaat terwijl elk vertrekkend lid nog een badge vrijspeelt voor de eerstvolgende aanmelder — precies het lek dat we dichten, op het moment dat de instroom het grootst is.
