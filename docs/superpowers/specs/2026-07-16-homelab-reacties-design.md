# Reacties op een homelab (spec B)

**Datum:** 2026-07-16
**Status:** ter review
**Bouwt op:** spec A (`2026-07-15-homelab-eigen-pagina-design.md`), net live. Elk homelab heeft nu een eigen pagina.

## Waarom

Een homelab heeft nu een eigen pagina met een feedback-vraag, maar niemand kan erop reageren. De hele bedoeling van spec A — dat de community elkaar stimuleert, dat je hulp krijgt op waar je om vroeg — mist zijn tweede helft. Dit is die helft: een gesprek onder een homelab, waarin de bouwer kan antwoorden zonder zijn anonimiteit te verliezen.

De maatstaf blijft eerlijk: er staan twee homelabs. Reacties maken die twee niet vanzelf tot een levende sectie. Maar zonder reacties is een feedback-vraag een brievenbus zonder gleuf. Dit maakt de gleuf.

De community moet **uplifting** blijven. Elke keuze hieronder is daarop geijkt: aanspreekbaarheid boven anonieme naarheid, de bouwer een uitweg maar geen censuurknop, en geen scores die een gesprek in een wedstrijd veranderen.

## Beslissingen

Vijf keuzes liggen vast (via de brainstorm) en sturen de rest.

### 1. Reageerders staan met naam; alleen de bouwer is pseudoniem

Wie een reactie plaatst, staat met zijn gebruikersnaam. Dat geeft aanspreekbaarheid — je zet je naam onder wat je zegt — en dat houdt een gesprek beschaafd. Alleen de **bouwer** blijft "de bouwer", want zijn hardware staat bij hem thuis; dat is het anonimiteitscontract uit spec A.

Het mechanisme is één regel, berekend bij het renderen:

```
label = (comment.user_id === post.user_id) ? "de bouwer" : comment.user.username
```

De bouwer die op **andermans** post reageert, staat daar gewoon met zijn naam — "de bouwer" geldt alleen op zijn eigen post. Er bestaat geen enkel publiek pad naar de gebruikersnaam of het e-mailadres van de bouwer. Dit is de belofte die stil kan breken, en krijgt daarom een expliciete test.

### 2. Eén niveau diep

Top-level reacties, met daaronder antwoorden (van de bouwer óf anderen). Eén niveau — een antwoord kan zelf geen antwoord krijgen. Genoeg voor een vraag-en-antwoord onder een homelab, en het blijft leesbaar op mobiel, waar diepe nesting een onleesbare trap wordt. Chronologisch getoond; geen scores op reacties (zie beslissing 5).

### 3. De bouwer sluit, de moderator wist

- **Iedereen** wist zijn eigen reactie.
- De **bouwer** kan de reacties op zijn post *sluiten* — de kolom `comments_open` bestaat al (spec A). Dat is zijn uitweg als het ontspoort. Bestaande reacties blijven staan; er komen geen nieuwe bij.
- De bouwer kan **géén** losse reacties van anderen wissen. Hij heeft om feedback gevraagd; hem laten cherry-picken zou "waar wil je feedback op" tot een lege uitnodiging maken.
- **Nare** reacties gaan via de bestaande meld-knop naar een **moderator**, die ze in Filament wist. Het `Report`-model is al polymorf (`reportable_type`/`reportable_id`, `morphTo`), dus een reactie melden hergebruikt de bestaande flow volledig.

**Moderatie-intern is de identiteit zichtbaar** — een moderator ziet in Filament de echte gebruiker achter een reactie, ook als die publiek "de bouwer" is. Dat moet, voor moderatie, en het is precies zoals posts nu al werken. Publiek verandert er niets.

### 4. Mail beide kanten

Via de bestaande mail-infra (`SellerContactMail` is het patroon: een bericht naar de e-mail van een gebruiker):

- Iemand reageert op een homelab → mail naar de **bouwer**.
- Iemand antwoordt op een reactie → mail naar die **reageerder**.

De mail bevat een **link naar de post**, niet de inhoud van de reactie, en **nooit** wie de bouwer is. Naar de bouwer: "er is een reactie op je homelab." Naar een reageerder: "de bouwer reageerde op je vraag." Geen van beide lekt een identiteit — de mail naar de bouwer gaat naar zijn eigen adres, de mail naar de reageerder noemt slechts "de bouwer".

Zonder deze mails werkt de feature niet: hoort de bouwer nooit dat iemand iets vroeg, dan sterft het gesprek na één zin.

### 5. Geen scores op reacties

Reacties zijn een gesprek onder de post, chronologisch. Alleen de **post** krijgt waarderingen — dat is de "content". Bij een draadje met een handvol reacties is ranken zinloos, en "beste reactie bovenaan" verandert een gesprek in een wedstrijd. Kan er later bij als draden ooit groot worden.

## Data

### `homelab_comments` — nieuwe tabel

```php
$t->id();
$t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
$t->foreignId('user_id')->constrained()->cascadeOnDelete();
// Eén niveau: een top-level reactie heeft parent_id null; een antwoord wijst
// naar een top-level reactie. Een antwoord mag zelf geen parent-van worden —
// dat wordt afgedwongen bij het aanmaken, niet door het schema.
$t->foreignId('parent_id')->nullable()->constrained('homelab_comments')->cascadeOnDelete();
$t->text('body');
$t->softDeletes();          // wissen (eigen of moderator) verbergt, verwijdert niet meteen
$t->timestamps();
$t->index(['homelab_post_id', 'created_at']);
```

**Waarom `softDeletes` en niet hard verwijderen:** een top-level reactie met antwoorden mag niet spoorloos verdwijnen — dan hangen de antwoorden in de lucht. Een zachtverwijderde reactie mét antwoorden rendert als `[verwijderd]` zodat de draad leesbaar blijft; zonder antwoorden valt hij gewoon weg. Eén mechanisme dekt zowel "wis mijn eigen reactie" als "moderator verwijdert". Moderator-verwijderingen worden gelogd via de bestaande `AdminActionLogger`, net als bij posts.

`comments_open` op `homelab_posts` bestaat al (spec A) en wordt hier eindelijk gelezen.

### `HomelabComment` — model

Relaties: `post()` (belongsTo), `user()` (belongsTo — voor moderatie, nooit publiek gerenderd voor de bouwer), `parent()` (belongsTo self), `replies()` (hasMany self, geordend op `created_at`). Implementeert de reportable-morph zodat het `Report`-model het aankan.

De auteur-label-logica (`de bouwer` vs username) hoort in de rendering, berekend uit `user_id === post.user_id`. Voor de bouwer wordt de username **nooit** aangeraakt; voor reageerders is de username het label (dat is het ontwerp).

## De reactie-sectie op de pagina

Een Livewire-component onder de bestaande homelab-detailpagina. Toont:

- De reacties, top-level chronologisch, elk met hun antwoorden eronder (één niveau).
- Per reactie: het label (`de bouwer` of username), de relatieve tijd, de body, en — voor de eigen reactie — een wis-knop, en voor iedereen een meld-knop (het `<details>`+POST-patroon uit spec A).
- Een formulier om een top-level reactie te plaatsen, en een antwoord-formulier per reactie — alleen zichtbaar als `comments_open` en de bezoeker is ingelogd.
- Voor de bouwer op zijn eigen post: een knop om de reacties te sluiten/openen.
- Is `comments_open` false: de bestaande reacties blijven zichtbaar, geen invoervelden, met een regel "de bouwer heeft reacties gesloten".

**Wie mag plaatsen:** ingelogde gebruikers. Dit matcht wat posten vereist (`Feed::submit` doet `abort_unless(auth()->check(), 403)`) — geen striktere regel verzinnen. Een uitgelogde bezoeker ziet de reacties en een "log in om te reageren"-verwijzing.

**Rate-limit tegen spam:** in het bestaande `RateLimiter`-patroon (zoals `Feed::submit`), bijv. enkele reacties per minuut per gebruiker. Exacte waarde bij de implementatie; de melding keyt op een veld dat het formulier toont (de les uit spec A: een foutmelding op de verkeerde sleutel is onzichtbaar).

**N+1 vermijden, proactief:** de reactie-query laadt `user` en `replies.user` eager (`->with(...)`), en de rendering gebruikt property-access op relaties, niet de relatie-methode. Dit is exact de N+1 die spec A's eindreview ving; hier vangen we hem vóóraf, mét een regressietest die faalt zonder de eager-load.

## Notificaties

Eén queued Mailable (`HomelabCommentMail`), geparametriseerd op ontvanger + type:

- **naar de bouwer** bij een nieuwe top-level reactie op zijn post;
- **naar de reageerder** bij een antwoord op zijn reactie.

Verstuurd binnen dezelfde flow als het opslaan van de reactie (via de queue, zoals de andere Mailables). Bevat een link naar de post-URL en het type gebeurtenis in gewone taal — geen reactie-inhoud, geen identiteit van de bouwer. Stuur **geen** mail naar jezelf (de bouwer die zijn eigen post beantwoordt, of een reageerder die op zijn eigen reactie voortborduurt).

## Melden

`HomelabComment` wordt reportable. Een nieuwe route `reports.homelab-comment.store` (POST, `{comment}`) en een controller-methode die `ReportController::storeForHomelabPost` spiegelt. In Filament: gemelde reacties zijn zichtbaar en een moderator kan er één verwijderen (zacht) — mirror van de `remove`-actie op `HomelabPostResource`, met een `AdminActionLogger`-regel.

## Wat er niet in zit

- **Scores op reacties.** Beslissing 5.
- **Diepe threads.** Beslissing 2 — één niveau.
- **Karma voor reacties.** `KarmaService` bestaat, maar of een reactie karma verdient is een aparte gamification-vraag (en farmbaar). Bewust buiten v1; kan later.
- **Een in-app notificatiesysteem.** Er is er geen, en mail dekt de lus. Bouwen we niet voor deze feature.
- **De bouwer die losse reacties van anderen wist.** Beslissing 3 — dat is moderatie, geen bouwer-macht.

## Risico's

**Twee posts blijven twee posts.** Zoals bij spec A: reacties zijn de gleuf, geen garantie dat er post ingaat. Als het stil blijft, is dat informatie over deelname, niet over de code.

**Anonimiteit lekt het makkelijkst hier.** Elke plek waar een reactie of notificatie de bouwer aanraakt is een kans op een lek. Daarom: de label-berekening raakt de username van de bouwer nooit aan, de mail naar reageerders noemt alleen "de bouwer", en een test asserteert de afwezigheid van de bouwer-username én -e-mail op de pagina én in de mail-inhoud.

**Mail als spam-vector.** Veel reacties = veel mail. Bij deze schaal geen probleem, maar de mails horen op termijn een uitschrijf-/voorkeursoptie te krijgen. Buiten v1; genoemd zodat het niet vergeten wordt.

**Een reactie melden kan als silencing-knop werken.** Melden verbergt niets automatisch — het zet een reactie in de moderatie-wachtrij; een mens beslist. Zo kan één melder een reactie niet wegkrijgen. Dat is bewust: melden is een signaal, geen delete.

## Testbaarheid

- De reactie-sectie toont **nergens** de gebruikersnaam of het e-mailadres van de bouwer — expliciet asserten, op de pagina én in de notificatie-mail.
- Een reactie van de bouwer op zijn eigen post rendert als "de bouwer"; dezelfde gebruiker op andermans post rendert met zijn naam.
- Een antwoord kan geen antwoord krijgen (één niveau afgedwongen).
- `comments_open = false`: bestaande reacties zichtbaar, geen invoervelden, geen nieuwe reactie mogelijk (ook niet via een directe call — server-side afgedwongen, niet alleen de UI verbergen).
- Een gebruiker wist zijn eigen reactie; een ander kan dat niet (403). De bouwer kan een reactie van een ander niet wissen.
- Een zachtverwijderde reactie mét antwoorden toont `[verwijderd]`; zonder antwoorden valt hij weg.
- Een nieuwe reactie stuurt de bouwer een mail; een antwoord stuurt de reageerder een mail; niemand krijgt mail over zijn eigen reactie.
- De reactie-query is niet N+1: één query voor de reacties, één voor hun users, niet één per reactie (regressietest, faalt zonder de eager-load).
- Een gemelde reactie verschijnt in de moderatie-flow; een moderator kan hem verwijderen.
