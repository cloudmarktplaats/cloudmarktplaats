# Elk homelab een eigen pagina

**Datum:** 2026-07-15
**Status:** ter review
**Volgende spec:** reacties op een homelab (spec B) — zit hier bewust niet in

## Waarom

De homelab-sectie moet de plek zijn waar de community elkaar stimuleert: laten zien wat je gebouwd hebt, en er iets aan hebben. Nu is een homelab één foto plus een korte tekst in een feed, zonder eigen plek. Je kunt er niet naar linken, er is niets te lezen, en er valt weinig zinnigs over te zeggen.

Eerlijk over de uitgangspositie: **er staan twee homelabs op productie, met vier waarderingen samen.** Een rijkere pagina vult die sectie niet vanzelf — mensen posten niet omdat de pagina mooier is. Wat een pagina wél levert is een reden om te posten: een eigen URL die je kunt delen. "Mijn homelab staat hier." Dat is de weddenschap, en het is meteen de maatstaf: staan er over twee weken nog steeds twee, dan lag het niet aan de pagina.

Dit is spec A van twee. Reacties (spec B) kunnen niet zonder een pagina om op te staan.

## Beslissingen

Vier keuzes liggen vast en sturen de rest.

### De bouwer blijft anoniem — met een pseudoniem

Het anonimiteitscontract blijft ongewijzigd: `user_id` bestaat voor rate-limits, eigen-post-verwijderen en moderatie, en wordt nooit publiek gerenderd. Dat is geen willekeur — mensen tonen dure hardware die bij hen thuis staat, en attributie is daar een inbraak- en doxxing-risico. Het staat bovendien als belofte in de FAQ: *"de feed is volledig anoniem — bezoekers zien nooit wie postte."*

In spec B krijgt de bouwer een pseudoniem ("de bouwer") zodat hij op vragen kan antwoorden zonder zichzelf bloot te geven. Deze spec voegt geen enkele naam toe, nergens.

### Geen downvotes

Waarderingen blijven upvote-only, precies zoals de FAQ belooft. Een downvote op iemands homelab is een vreemde die zonder uitleg zegt dat jouw ding slecht is; dat is het tegenovergestelde van de uplifting community die we willen, en het raakt iets persoonlijks. Rommel en naarheid gaan via de meld-knop die er al is (`ReportController::storeForHomelabPost`) plus moderatie — niet via een knop waarmee iedereen elkaars werk omlaag duwt.

### Vrije tekst, geen specs-formulier

Titel, markdown-verhaal, meerdere foto's, plus één optioneel veld: *"waar wil je feedback op?"*. Geen vaste velden voor hardware, verbruik of OS. Reden: elke drempel kost posts, en bij twee posts is elke drempel er één te veel. De bouwer bepaalt zelf hoe diep hij gaat; dat ene feedback-veld stuurt de toon zonder een formulier van twaalf velden te worden.

### Titel mag leeg

De twee bestaande posts hebben geen titel en moeten blijven werken zoals ze zijn. `title` is dus nullable. Waar een titel ontbreekt toont de pagina een fragment van de body (eerste regel, afgekapt); dat is ook wat in de `<title>` en de og:title terechtkomt. Niemand hoeft zijn oude post aan te passen.

## De pagina

Route: `/homelabs/{ulid}-{slug}`, dezelfde vorm als advertenties. De slug komt uit de titel; zonder titel is het alleen de ulid. Binding gebeurt op `ulid` — de slug is cosmetisch en wordt niet gecontroleerd, zoals bij advertenties.

Op de pagina staat, van boven naar beneden:

```
PROXMOX-CLUSTER OP DRIE ELITEDESKS        3 dagen geleden

  [foto 1]  [foto 2]  [foto 3]

  ## Wat draait erop
  Proxmox 8, 3 nodes, Ceph. TrueNAS in een VM,
  Home Assistant, wat *arr-spul.

  ┌──────────────────────────────────────────┐
  │ De bouwer vraagt feedback op:            │
  │ Idle-verbruik is 38W. Kan dat lager?     │
  └──────────────────────────────────────────┘

  ▲ 24 waarderingen                 [ ⚑ melden ]
```

De feedback-vraag staat in een eigen kader, niet verstopt in de body: hij bepaalt waar de reacties in spec B over gaan. Ontbreekt hij, dan verdwijnt het kader.

De feed (`/homelabs`) blijft wat hij is, met één wijziging: elke kaart linkt door naar zijn eigen pagina. Waarderen blijft ook vanuit de feed werken.

## Data

### `homelab_posts` — drie kolommen erbij

- `title` — `string(120)`, nullable. Zie hierboven.
- `feedback_prompt` — `string(280)`, nullable. Kort met opzet: het is een vraag, geen tweede body.
- `comments_open` — `boolean`, default `true`. Wordt in deze spec alleen geschreven bij het plaatsen en nergens gelezen; spec B gebruikt hem. Hij zit hier omdat het een kolom op deze tabel is en één migratie goedkoper is dan twee.

### `homelab_photos` — nieuwe tabel

Een exacte spiegel van `listing_photos`, want dat patroon werkt en de regels zijn dezelfde:

```php
$t->id();
$t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
$t->string('disk', 16)->default('local');
$t->string('path');                        // wijst naar de card-variant
$t->unsignedSmallInteger('width');
$t->unsignedSmallInteger('height');
$t->string('mime', 64);                    // bron-mime; hiermee is original.{ext} te bouwen
$t->unsignedInteger('byte_size');
$t->unsignedTinyInteger('position');
$t->timestamps();
$t->unique(['homelab_post_id', 'position']);
```

**De `mime`-kolom is de kern van deze tabel, niet een detail.** `HomelabPost::photoUrl()` gooit vandaag bewust een exception voor de `original`-variant, en het docblock legt precies uit waarom:

> *"If posts ever need a shareable og:image, add a `mime` column (as listing_photos has) — then the original becomes buildable."*

Zonder mime is de bron-extensie nergens uit terug te halen: `photo_path` wijst altijd naar de card (webp), dus `pathinfo()` levert altijd "webp" en bouwt `original.webp` voor een bestand dat `original.jpg` heet — een URL die 404t. Dat was de bug in `ListingPhoto` (gefixt 14-07) en de reden dat homelab-foto's nu geen deelbare afbeelding hebben. Deze spec lost dat op bij de bron.

Na deze migratie zijn `homelab_posts.photo_disk` en `photo_path` dood. Ze worden **niet** in deze spec verwijderd: eerst wil ik de nieuwe tabel een week in productie zien. Ze blijven staan, ongelezen, en gaan in een opruim-migratie later weg.

### Wat er met `photoUrl()` gebeurt

`HomelabPost::photoUrl()` heeft vier gebruikers: de feed, het "recent"-blok op de homepage, de Filament-moderatiekolom, en een testbestand dat zijn contract vastlegt. Die blijven allemaal werken.

Er komt een `HomelabPhoto`-model met `urlFor(string $variant)`, gespiegeld op `ListingPhoto` — inclusief de `extForMime()`-mapping, die daar bewust naast de lezer staat in plaats van naast de schrijver, omdat twee kopieën uiteen lopen en de lezer dan 404t.

`HomelabPost::photoUrl()` wordt een dunne doorgeefluik naar de eerste foto (`photos()->first()`) en behoudt zijn signatuur. Zo hoeven feed, recent-blok en Filament niet te wijzigen, en is er één plek die weet hoe een variant-URL eruitziet. Heeft een post geen foto's — kan niet via het formulier, wel via een half mislukte migratie — dan gooit hij, net als nu: een dode URL is erger dan een duidelijke fout.

**Het contract van dat testbestand verandert, en dat is het punt.** `HomelabPhotoUrlTest` legt nu vast dat `photoUrl('original')` een exception gooit omdat de bron-mime nergens staat. Met de `mime`-kolom is `original` wél te bouwen, dus die test moet zeggen wat er nu geldt: `original` werkt voor een jpeg/png-bron, en gooit nog steeds voor een variant die niet bestaat. Dat is geen test die "in de weg zit" — hij documenteerde een gemis dat we hier opheffen, precies zoals zijn eigen docblock aankondigde.

## Foto's

`StoreHomelabPhotoJob` schrijft nu één foto naar `homelabs/{post_ulid}/{variant}.{ext}` en zet het card-pad in `homelab_posts`. Hij gaat per foto draaien en schrijft een `homelab_photos`-rij met `position`, in plaats van de post bij te werken. De varianten blijven wat ze zijn (original max 2000px in de bron-mime, card en thumb als webp) en EXIF wordt gestript zoals nu.

Grenzen komen uit `config('cloudmarktplaats.photos')` — dezelfde 8 MB en maximaal 10 als advertenties. Eén getal voor beide, want er zijn al drie plekken waar die grens geldt (nginx, PHP's ini, de validatieregel) en de strengste wint stil.

**Aandachtspunt bij de uitrol:** het homelab-formulier heeft dezelfde stille upload-bug als de advertentiewizard — een mislukte upload laat het veld leeg en zegt niets. De fix daarvoor staat klaar en getest maar nog niet op productie (commits `5055208` en `5b3e816`). Van één naar tien foto's gaan vergroot dat probleem precies daar. Die fix hoort vóór deze feature live, en het formulier krijgt dezelfde behandeling: voortgang, leesbare uploadfouten, en te grote bestanden meteen gemeld.

## Delen

De pagina krijgt volledige OG-tags via de bestaande `marketing`-layout:

- `og:title` — de titel, of het body-fragment als er geen titel is.
- `og:description` — begin van de body, platte tekst.
- `og:image` — `original.{ext}` van de eerste foto, mits die `image/jpeg` of `image/png` is. Is de bron webp, dan valt hij terug op `og-default.png`. Reden: LinkedIn rendert geen webp — dezelfde regel die de advertentiepagina al hanteert.

Dit is waarom de feature bestaat: een homelab wordt iets om te delen.

## De twee bestaande posts

Ze houden hun foto en hun body, krijgen geen titel, en blijven werken.

De migratie zet voor elk van de twee een `homelab_photos`-rij op `position` 0, met `disk` en `path` uit de bestaande kolommen. `width`, `height` en `byte_size` worden uit het card-bestand op de schijf gelezen.

Voor `mime` geldt: **niet gokken naar de bron.** De oorspronkelijke extensie is niet meer te achterhalen, en een gok bouwt exact de 404 die we net beschreven. De migratie zet daarom `image/webp` — de mime van het bestand waar `path` werkelijk naar wijst. Gevolg: `og:image` valt voor deze twee terug op `og-default.png`, precies zoals de webp-regel hierboven voorschrijft. Dat is de eerlijke uitkomst: een merkbeeld in plaats van een kapotte link. Hun galerij toont gewoon de card, die browsers prima renderen.

Nieuwe posts hebben dit probleem niet: die schrijven hun echte bron-mime weg.

## Wat er niet in zit

- **Reacties.** Spec B.
- **Downvotes.** Zie hierboven; die komen er niet.
- **De naam van de bouwer.** Nooit.
- **Specs-velden** (hardware, verbruik, OS). Bewust niet; het is invulwerk en het zou het aantal posts eerder verlagen dan verhogen. Blijkt later dat mensen dit in hun body typen, dan is dát het bewijs dat het veld mag bestaan.
- **Bewerken van een homelab-post.** Bestaat nu ook niet (alleen plaatsen en verwijderen) en verandert hier niet.
- **Opruimen van `photo_disk` / `photo_path`.** Later, als de nieuwe tabel zich bewezen heeft.

## Risico's

**Twee posts blijven twee posts.** Het echte risico. De pagina is een reden om te posten, geen garantie. Als dit niets doet, is de volgende vraag niet "hoe maken we de pagina beter" maar "waarom laat een homelabber zijn rack niet zien" — en dat is een vraag voor de community, niet voor de code.

**Meer foto's, meer stille uploads.** Zie het aandachtspunt hierboven. Deze feature bouwen bovenop een upload die zwijgend faalt levert gegarandeerd de volgende bugmelding op.

**De feedback-vraag kan als uitnodiging tot kritiek werken.** Hij is optioneel en de bouwer schrijft hem zelf, dus hij bepaalt waar het over gaat. Maar hij staat in een uitgelicht kader, en dat trekt aandacht. Blijkt hij averechts te werken, dan is het kader weghalen één regel.

**Een dode kolom die niemand leest.** `comments_open` wordt in deze spec geschreven en nergens gelezen. Dat is bewust (één migratie in plaats van twee), maar als spec B er nooit komt, staat er een kolom die niets doet. Acceptabel: het is een boolean met een default.

## Testbaarheid

- De pagina rendert voor een post met titel én voor een post zonder titel (dan een body-fragment als kop).
- De pagina toont **nergens** de gebruikersnaam of het e-mailadres van de bouwer — expliciet asserten, want dit is de belofte uit de FAQ en de enige die stil kan breken.
- `og:image` wijst naar `original.jpg` voor een jpeg-bron, en naar `og-default.png` voor een webp-bron.
- De feedback-vraag verschijnt alleen als hij ingevuld is.
- Waarderen werkt vanaf de eigen pagina en blijft werken vanuit de feed.
- Een verwijderde post (`status = removed`) geeft een 404 op zijn eigen URL.
- De migratie geeft de twee bestaande posts een `homelab_photos`-rij met de juiste afmetingen, en laat ze verder ongemoeid.
- Meerdere foto's: een post met drie foto's toont ze in de volgorde van `position`.
- `photoUrl('card')` blijft doen wat het deed — de feed, het recent-blok en Filament renderen ongewijzigd.
- `photoUrl('original')` bouwt `original.jpg` voor een jpeg-bron (was: exception) en gooit nog steeds voor een onbekende variant.
