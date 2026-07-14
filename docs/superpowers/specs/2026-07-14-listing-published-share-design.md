# Delen na goedkeuring — publicatie-mail + share-paneel

**Datum:** 2026-07-14
**Status:** ontwerp goedgekeurd door Nick (chat), implementatieplan volgt

## Wat en waarom

Een advertentie die door moderatie komt, is precies op het moment dat de verkoper
gemotiveerd is om hem te delen. Nu gebeurt er niets: de state gaat naar `published`,
en de verkoper merkt dat pas als hij zelf gaat kijken. We sturen een korte mail
("je advertentie staat live") die naar een share-paneel op de eigen site leidt, met
UTM-parameters zodat we per kanaal zien wat een seller-share oplevert.

Besluiten uit de brainstorm:

1. **Share-knoppen op de site, niet in de mail.** De mail is kort en linkt door.
   Reden: kopiëren-naar-klembord werkt niet in mail (geen JS), de tekst is op de
   site aanpasbaar zonder dat verstuurde mails verouderen, en het paneel is
   herbruikbaar op detail + "Mijn advertenties".
2. **OG-tags per listing zijn onderdeel van deze feature, niet een extraatje.**
   Zonder dat is de rest zinloos (zie hieronder).
3. **Kanalen: LinkedIn + MainDeck.** MainDeck (`maindeck.eu`) is een Europees,
   privacy-gericht LinkedIn-alternatief.
4. **Eén `ShareLinkBuilder`** als enige plek waar UTM-strings bestaan.

## Het fundament: OG-tags per listing

`app/Livewire/Listings/Detail.php:31` zet `#[Layout('components.layouts.marketing')]`
maar geeft géén `title` / `description` / `ogImage` mee. De layout
(`resources/views/components/layouts/marketing.blade.php`) valt daardoor terug op zijn
`@props`-defaults. Concreet: **elke gedeelde advertentie toont nu op LinkedIn de titel
"Cloudmarktplaats — open source marktplaats voor tech" met `og-default.png`** — niet de
advertentie, niet de foto, niet de prijs.

Dit is ook waarom prefill-tekst geen oplossing is: **LinkedIn negeert `title`/`summary`/
`text` op `share-offsite/` sinds ~2021.** Wat in de post verschijnt komt volledig uit de
OG-tags van de gedeelde pagina. De OG-tags zijn dus de enige knop die we hebben.

### Aanpak

`Detail::render()` geeft layout-data mee:

```php
return view('livewire.listings.detail')->layoutData([
    'title'       => $this->listing->title.' — Cloudmarktplaats',
    'description' => $this->ogDescription(),
    'ogImage'     => $this->ogImageUrl(),
    'canonical'   => route('listings.detail', [
        'ulid' => $this->listing->ulid,
        'slug' => $this->listing->slug,
    ]),
]);
```

**Alleen voor `published` listings.** Voor draft / pending_review / rejected blijven de
layout-defaults staan, zodat een niet-publieke advertentie zijn inhoud niet via OG-tags
lekt. Dat sluit aan op de bestaande regel in `mount()`: niet-publieke listings zijn al
alleen zichtbaar voor eigenaar en staff.

**`og:image` gebruikt de `original`-variant, niet `card`.** De drie varianten uit
`StoreListingPhotoJob` zijn:

| variant    | formaat            | geschikt voor og:image? |
|------------|--------------------|-------------------------|
| `original` | max 2000px, bron-mime (jpg/png) | **ja** |
| `card`     | 600×600 webp       | nee — webp + vierkant |
| `thumb`    | 200×200 webp       | nee — te klein |

LinkedIn's crawler is onbetrouwbaar met WebP en wil ~1.91:1 (1200×627); `card` is
vierkant en webp. `original` is de enige variant die zeker werkt. Bij een listing
zonder foto's valt `ogImage` terug op `null` → de layout gebruikt `og-default.png`.

`ogDescription()` kort de beschrijving af op 155 tekens (`Str::limit`). De kolom
`description` is nullable, dus bij een lege beschrijving valt hij terug op een
samenstelling van categorie + staat + prijs (bijv. "Netwerk — gebruikt — € 450") in
plaats van een lege `og:description`, die LinkedIn als kale link zou tonen.

## ShareLinkBuilder

`app/Support/ShareLinkBuilder.php` — één plek waar UTM-strings leven, unit-testbaar,
geen query-strings verspreid over blades en mails.

```php
public function listingUrl(Listing $l, string $source, string $medium, string $campaign): string
public function linkedIn(Listing $l): string   // share-offsite/?url={listingUrl(...linkedin)}
public function mainDeck(Listing $l): string
public function shareText(Listing $l): string  // kant-en-klare tekst voor kopiëren
```

### UTM-schema

UTM's meten waar verkeer *vandaan* komt en horen op de bestemming (onze eigen listing-URL).
`utm_source=cloudmarktplaats` op onze eigen link zou zichzelf meten en is dus fout.

| context                | utm_source | utm_medium | utm_campaign        |
|------------------------|------------|------------|---------------------|
| deel-link LinkedIn     | `linkedin` | `social`   | `seller_share`      |
| deel-link MainDeck     | `maindeck` | `social`   | `seller_share`      |
| knop in publicatie-mail| `email`    | `email`    | `listing_published` |

De gedeelde URL is de canonieke `route('listings.detail')` (`/listings/{ulid}-{slug}`).
Let op de bestaande 301-redirect in `mount()`: bij een niet-matchende slug wordt
permanent geredirect — de builder moet dus altijd de actuele slug gebruiken, anders
verliest elke share een redirect-hop (en LinkedIn's crawler volgt die niet gegarandeerd).

## Share-paneel

Blade-component (`resources/views/components/listings/share-panel.blade.php`), geen
Livewire — er is geen server-state. Zichtbaar **alleen voor de eigenaar** van een
`published` listing, via de bestaande `ListingPolicy`. Plekken: detailpagina en
"Mijn advertenties" (`resources/views/livewire/listings/mine.blade.php`).

Drie acties:

1. **Deel op LinkedIn** → `linkedin.com/sharing/share-offsite/?url=...`
2. **Deel op MainDeck** → link naar `maindeck.eu` (zie open punt)
3. **Kopieer tekst + link** → Alpine `navigator.clipboard`, met fallback op een
   selecteerbaar `<input readonly>` als de Clipboard API niet beschikbaar is (http,
   oudere browsers). Dit is precies waarom het paneel op de site staat en niet in de mail.

## Publicatie-mail

- **Listener** `app/Listeners/Listings/SendListingPublishedMail.php`, geregistreerd via
  `Event::listen` in `AppServiceProvider::boot()` — naast de bestaande
  `AwardInviteKarmaOnFirstListing` op `ListingPublished`.
- **Mailable** `app/Mail/ListingPublishedMail.php` in het patroon van
  `SellerContactMail` (`Queueable`), plus `ShouldQueue` zodat de moderator in Filament
  niet op SMTP wacht.
- **View** `resources/views/emails/listing-published.blade.php`: titel, foto, één knop
  naar de listing met `utm_source=email`. Het paneel doet de rest.

### Randgevallen

- **Meerdere mails per listing is gewenst gedrag.** Via `rejected → draft →
  pending_review → published` vuurt `ListingPublished` opnieuw. Dat is juist: de
  advertentie is nu écht goedgekeurd. Geen dedupe-logica.
- **Bulk-publish in Filament** (`ListingResource`) roept per listing
  `ListingStateService::transition()` aan, dus elk event vuurt — N mails bij N listings.
  Correct, en de queue vangt het op.
- **Mail faalt ≠ publicatie faalt.** De listener draait op de queue; een SMTP-storing
  mag de state-transitie niet terugdraaien. `ListingStateService::transition()` heeft
  het event al gedispatcht ná `save()`.

## Testen

- **Unit** `ShareLinkBuilderTest`: UTM-params per kanaal, URL-encoding van de
  geneste `?url=`, canonieke slug.
- **Feature** `ListingPublishedMailTest`: `Mail::fake()`; transitie naar `published`
  → mail naar de eigenaar; transitie naar `rejected` → géén mail.
- **Feature** `ListingOgTagsTest`: published listing → `og:title` bevat de titel,
  `og:image` wijst naar de `original`-variant; listing zonder foto's → `og-default.png`;
  pending_review (als eigenaar bekeken) → layout-defaults, geen listing-data in de OG-tags.
- **Feature** `SharePanelTest`: eigenaar ziet het paneel, een andere gebruiker niet,
  en het paneel ontbreekt op een niet-published listing.

## Open punt

**MainDeck-prefill is onbevestigd.** `maindeck.eu/share` en `/compose` bestaan maar
redirecten naar `/login?next=%2Fshare`; of ze `?url=` / `?text=` accepteren is van
buitenaf niet te testen, en de `next=`-parameter behoudt geen query-string. MainDeck is
bovendien nog invite/waitlist, dus lang niet elke verkoper heeft een account.

Daarom: **v1 gebruikt een gewone link naar MainDeck plus de kopieerbare tekst.** Zodra
Nick bevestigt dat compose prefill accepteert, is dat één regel in `ShareLinkBuilder`
(en één test). Dit is expliciet geen blokkade voor de rest.

## Niet in scope

- Klik-tracking van de share-knoppen zelf (UTM's meten het resultaat, dat volstaat).
- Andere kanalen (Mastodon, WhatsApp, X). Toevoegen is later één methode in de builder.
- API-integratie of namens de gebruiker posten (OAuth-tokens, scopes, rate limits).
- Een og:image-generator die prijs/titel in de foto brandt.
