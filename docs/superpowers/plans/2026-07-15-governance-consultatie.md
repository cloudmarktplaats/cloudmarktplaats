# Governance: zeggen wat waar is — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De site laten zeggen wat er werkelijk waar is over eigenaarschap en beslissen — consultatie, geen stemrecht — en dat proces op één vindbare plek vastleggen.

**Architecture:** Twee tekstwijzigingen en één nieuw document. Op `/waarden` blijft de kop "De community bezit het platform" staan (die is waar) en wordt de uitleg eronder eerlijk over wat dat wél en niet betekent. `docs/GOVERNANCE.md` beschrijft het besluitproces, en is vanaf `/waarden` te vinden via de bestaande voetrij. Geen stemsysteem, geen nieuwe pagina, geen nieuwe tabel.

**Tech Stack:** Laravel 11, Blade, Pest, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-07-15-governance-consultatie-design.md`

## Global Constraints

- Alles draait in Docker; de host heeft geen PHP. Tests: `docker compose exec -T php-fpm ./vendor/bin/pest`.
- Kwaliteitspoorten moeten groen blijven: `docker compose exec -T php-fpm ./vendor/bin/pint --test` en `docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G` (level 8; zonder die memory-limit crasht hij).
- NL is de brontaal én de vertaalsleutel. Elke nieuwe zichtbare string krijgt een regel in `lang/en.json` met de Nederlandse zin als key. Verifieer EN via `artisan tinker`, niet via curl: de locale is sessie-gebonden (`/taal/{locale}`), er is geen `/en`-URL.
- `resources/views/pages/values.blade.php` rendert de uitleg als `{{ $body }}` — **escaped**. Geen HTML, geen links in die tekst. Een link hoort in de voetrij onderaan de pagina.
- De kop `De community bezit het platform.` blijft **letterlijk ongewijzigd**. Alleen de uitleg eronder verandert. Die kop is waar; hem afzwakken zou het tegenovergestelde zijn van wat deze wijziging doet.
- De overige elf waarden blijven ongemoeid. `$values` is één array; één regel wijzigen mag de rest niet raken.
- Het sponsor-stemrecht op `/sponsors` blijft ongewijzigd. Dat gaat over het sponsorfonds, en valt buiten deze wijziging.

---

## File Structure

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `resources/views/pages/values.blade.php` | De eerlijke uitleg + de vindbare link | 1, 2 |
| `lang/en.json` | Engelse vertaling van beide | 1, 2 |
| `docs/GOVERNANCE.md` | Het besluitproces: wie beslist, waarover, en waar | 2 |
| `tests/Feature/Pages/ValuesPageTest.php` | Bewijst dat de pagina zegt wat waar is | 1, 2 |

---

### Task 1: `/waarden` zegt wat waar is

**Achtergrond voor de implementer:** op `/waarden` staat live "De community bezit het platform — Geen aandeelhouders, geen exit, geen overname." Op `/sponsors` staat, even live, dat betalende sponsors stemrecht krijgen op de jaarlijkse vergadering. Leden hebben niets. De zin is niet gelogen (de code is AGPL, er zijn geen aandeelhouders, iedereen kan forken), maar naast die sponsorpagina nodigt hij uit tot een lezing die niet waar is: dat de community beslist. Deze taak maakt de uitleg precies.

**Files:**
- Modify: `resources/views/pages/values.blade.php:5`
- Modify: `lang/en.json:225`
- Test: `tests/Feature/Pages/ValuesPageTest.php` (nieuw)

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: niets voor latere taken. Taak 2 voegt aan hetzelfde testbestand toe.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Pages/ValuesPageTest.php` aan:

```php
<?php

declare(strict_types=1);

/*
 * /waarden zegt live dat de community het platform bezit, terwijl /sponsors
 * even live stemrecht aan betalende sponsors belooft en leden niets hebben.
 * De kop is waar — AGPL, geen aandeelhouders, geen exit, iedereen kan forken —
 * maar de uitleg eronder moet zeggen wat dat wél en niet betekent, anders leest
 * de pagina als zeggenschap die er niet is.
 */
it('says what ownership does and does not mean', function () {
    $page = $this->get('/waarden');

    $page->assertOk()
        // De kop blijft: die is waar en wordt niet afgezwakt.
        ->assertSee('De community bezit het platform.')
        // Wat eigenaarschap wél is: het recht om weg te lopen met alles.
        ->assertSee('De code is AGPL')
        ->assertSee('het recht om weg te lopen met alles')
        // En wat het niet is. Zonder deze zin leest de kop als zeggenschap.
        ->assertSee('maar we stemmen er niet over');
});

it('keeps the other eleven values intact', function () {
    // $values is één array; één regel wijzigen mag de rest niet raken.
    $page = $this->get('/waarden');

    $page->assertOk()
        ->assertSee('Privacy is een ontwerpkeuze, geen marketing.')
        ->assertSee('Open source, AGPL.')
        ->assertSee('Geen algoritmische manipulatie.')
        ->assertSee('Geen activisme-performance.');
});
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/ValuesPageTest.php`

Expected: de eerste test FAILT op `assertSee('De code is AGPL')` — die tekst staat er nog niet. De tweede test PASSEERT al (de elf andere waarden staan er gewoon); dat is de bedoeling — hij is er om te bewijzen dat je ze in stap 3 niet sloopt.

- [ ] **Step 3: Maak de uitleg eerlijk**

Vervang in `resources/views/pages/values.blade.php` regel 5:

```php
        [__('De community bezit het platform.'), __('Geen aandeelhouders, geen exit, geen overname.')],
```

door:

```php
        [__('De community bezit het platform.'), __('Geen aandeelhouders, geen exit, geen overname. De code is AGPL: je kunt hem forken en zonder ons verder. Keuzes leggen we openbaar voor en we vertellen wat we ermee deden — maar we stemmen er niet over. Eigenaarschap is hier het recht om weg te lopen met alles, niet het recht om ons te overstemmen.')],
```

Let op: de kop is teken voor teken gelijk gebleven. Alleen het tweede element van het paar is vervangen.

- [ ] **Step 4: Vervang de Engelse vertaling**

De oude sleutel `"Geen aandeelhouders, geen exit, geen overname."` wordt nergens anders gebruikt — geverifieerd: `values.blade.php:5` was de enige plek. Verwijder regel 225 uit `lang/en.json`:

```json
    "Geen aandeelhouders, geen exit, geen overname.": "No shareholders, no exit, no acquisition.",
```

Voeg op diezelfde plek toe, direct onder `"De community bezit het platform.": "The community owns the platform.",`:

```json
    "Geen aandeelhouders, geen exit, geen overname. De code is AGPL: je kunt hem forken en zonder ons verder. Keuzes leggen we openbaar voor en we vertellen wat we ermee deden — maar we stemmen er niet over. Eigenaarschap is hier het recht om weg te lopen met alles, niet het recht om ons te overstemmen.": "No shareholders, no exit, no acquisition. The code is AGPL: you can fork it and carry on without us. We put choices out in the open and tell you what we did with your input — but we don't put them to a vote. Ownership here is the right to walk away with everything, not the right to outvote us.",
```

Het bestand moet geldige JSON blijven — let op de komma's.

- [ ] **Step 5: Draai de tests en zie ze slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/ValuesPageTest.php`

Expected: beide tests PASS.

- [ ] **Step 6: Controleer de Engelse tekst en de JSON**

Run:

```bash
docker compose exec -T php-fpm php -r 'json_decode(file_get_contents("lang/en.json"), true, 512, JSON_THROW_ON_ERROR); echo "geldige JSON\n";'
docker compose exec -T php-fpm php artisan tinker --execute="app()->setLocale('en'); echo __('Geen aandeelhouders, geen exit, geen overname. De code is AGPL: je kunt hem forken en zonder ons verder. Keuzes leggen we openbaar voor en we vertellen wat we ermee deden — maar we stemmen er niet over. Eigenaarschap is hier het recht om weg te lopen met alles, niet het recht om ons te overstemmen.');"
```

Expected: `geldige JSON`, gevolgd door de Engelse zin die begint met `No shareholders, no exit, no acquisition. The code is AGPL:`.

Krijg je de Nederlandse zin terug, dan wijkt de sleutel af van de string in de blade — meestal het gedachtestreepje (`—`, U+2014) of een dubbele spatie. De sleutel moet teken voor teken gelijk zijn.

- [ ] **Step 7: Commit**

```bash
git add resources/views/pages/values.blade.php lang/en.json tests/Feature/Pages/ValuesPageTest.php
git commit -m "docs: /waarden zegt wat eigenaarschap wel en niet betekent

De kop klopt — AGPL, geen aandeelhouders, geen exit, iedereen kan forken.
Maar naast /sponsors, waar betalende sponsors stemrecht krijgen terwijl
leden niets hebben, leest hij als zeggenschap die er niet is.

De uitleg zegt nu wat eigenaarschap hier is: het recht om weg te lopen met
alles, niet het recht om ons te overstemmen."
```

---

### Task 2: `docs/GOVERNANCE.md` en de weg ernaartoe

**Achtergrond voor de implementer:** de tekst op `/waarden` zegt nu dat keuzes openbaar worden voorgelegd. Dat moet ergens staan, anders is het een bewering zonder inhoud. Dit document is die inhoud. Het is bewust kort: er wordt niets gebouwd, er is geen stemsysteem, en het beschrijft een praktijk die vanaf nul begint.

**Files:**
- Create: `docs/GOVERNANCE.md`
- Modify: `resources/views/pages/values.blade.php:46-50` (de bestaande voetrij)
- Modify: `lang/en.json`
- Test: `tests/Feature/Pages/ValuesPageTest.php` (toevoegen aan het bestand uit Taak 1)

**Interfaces:**
- Consumes: `tests/Feature/Pages/ValuesPageTest.php` bestaat al (Taak 1); voeg toe, herschrijf niet.
- Produces: niets voor latere taken.

- [ ] **Step 1: Schrijf de falende test**

Voeg onderaan `tests/Feature/Pages/ValuesPageTest.php` toe:

```php
it('points at the governance document', function () {
    // "Keuzes leggen we openbaar voor" is een bewering; zonder een vindbare
    // uitleg van hoe dat gaat is het een bewering zonder inhoud.
    $this->get('/waarden')
        ->assertOk()
        ->assertSee('→ Hoe we beslissen')
        ->assertSee('blob/main/docs/GOVERNANCE.md', escape: false);
});

it('ships the governance document it points at', function () {
    // De og-default.png-les: een verwijzing zonder bestand faalt nergens,
    // je ziet het alleen als je erop klikt.
    expect(base_path('docs/GOVERNANCE.md'))->toBeFile();
});
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/ValuesPageTest.php`

Expected: de twee nieuwe tests FALEN — de link staat er niet en het bestand bestaat niet. De twee tests uit Taak 1 blijven PASSEN.

- [ ] **Step 3: Schrijf het document**

Maak `docs/GOVERNANCE.md` aan:

```markdown
# Hoe we beslissen

> Cloudmarktplaats is open source en heeft geen aandeelhouders. Dat betekent niet
> dat de community stemt. Het betekent dat je alles kunt zien, alles kunt zeggen,
> en met de hele boel kunt vertrekken als je het beter kunt.

## Consultatie, geen stemrecht

Keuzes die ertoe doen gaan openbaar vóórdat ze vastliggen. Wie wil kan reageren.
Nick beslist, en vertelt wat hij met de reacties deed — ook, en vooral, als hij
afwijkt.

Waarom geen bindende stemming: dit project heeft een uitgesproken smaak
("datasheet, geen startup", geen trackers, geen algoritmische manipulatie).
Precies die smaak is stembaar, en de eerste keer dat een meerderheid "kunnen we
niet gewoon aanbevelingen doen" wint, is het platform weg waarvoor mensen kwamen.
Een stemming die alleen telt als de uitkomst bevalt, is geen stemming maar
theater. Dan is consultatie eerlijker.

De prijs staat hier expliciet: dit is geen zeggenschap. Consultatie zonder gevolg
is erger dan niets vragen. Daarom is die laatste stap — vertellen wat er met je
reactie gebeurde — geen beleefdheid maar de hele afspraak.

## De founding-badge geeft geen macht

De eerste honderd leden dragen een founding-badge. Die zegt *wanneer je
binnenkwam*, niet *wat je bijdraagt*. Er zeggenschap aan hangen zou lid #43 dat
nooit iets postte meer te zeggen geven dan lid #137 die drie homelabs plaatst en
een bug meldt. Dat is een aristocratie op aankomsttijd, en het spreekt tegen waar
dit platform voor bestaat.

De badge is een eerlijk historisch feit. Dat is genoeg, en dat is alles.

## Niet alles gaat de deur uit

Er is verschil tussen *wat er waar is* en *waar we heen gaan*.

**Nooit ter consultatie** — dit is werk, geen mening:

- bugs en kapotte dingen;
- privacy, beveiliging, AVG;
- eerlijkheid van wat we tonen (een teller die liegt gaat weg, ook als iemand hem
  mooi vindt);
- wat wettelijk moet;
- individuele moderatiebesluiten — die zijn appellabel, niet stembaar. Anders
  wordt moderatie een populariteitswedstrijd over een echt persoon.

**Wel ter consultatie:** richting, functionaliteit, geld, waarden, en de vraag wat
we niet bouwen.

Zonder die grens verdrinkt de echte vraag in ruis. Een upload die stil faalt en de
verkoper de schuld geeft is geen kwestie waar je over stemt; die repareer je.

## Waar het gebeurt

Er is geen apart platform en geen stemknop. De ontwerpdocumenten staan al
openbaar in deze repo, in het Nederlands, mét het waarom — in
`docs/superpowers/specs/`. Dat is de bron.

Per keuze die ertoe doet:

1. De spec staat in de repo en verandert niet.
2. Een LinkedIn-post vat hem samen in gewone taal en vraagt om reacties. Daar
   zitten de meeste leden.
3. Een GitHub-draad voor wie de details in wil.
4. Reacties tellen, waar ze ook binnenkomen: LinkedIn, GitHub, e-mail, DM.
5. Nick beslist en publiceert wat hij ermee deed.

Stap 5 is de enige die niet mag ontbreken.

Waarom niet alleen GitHub: van de leden heeft ongeveer één op de zes een
GitHub-account. Wie daar begint en eindigt praat met een zesde van de community en
noemt dat "de community".
```

- [ ] **Step 4: Voeg de link toe aan de voetrij**

Vervang in `resources/views/pages/values.blade.php` de voetrij op regel 46-50:

```blade
        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('about') }}" class="hover:text-cmp-blue">{{ __('→ Over ons') }}</a>
            <a href="{{ route('faq') }}" class="hover:text-cmp-blue">{{ __('→ Veelgestelde vragen') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-blue" rel="noopener external">{{ __('→ Code op GitHub') }}</a>
        </div>
```

door:

```blade
        <div class="mt-14 pt-6 border-t border-cmp-border font-mono text-[11px] text-cmp-muted flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('about') }}" class="hover:text-cmp-blue">{{ __('→ Over ons') }}</a>
            <a href="{{ route('faq') }}" class="hover:text-cmp-blue">{{ __('→ Veelgestelde vragen') }}</a>
            {{-- De uitleg hierboven belooft dat keuzes openbaar voorgelegd worden;
                 dit is waar dat staat. Lezen kan zonder GitHub-account. --}}
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats/blob/main/docs/GOVERNANCE.md" class="hover:text-cmp-blue" rel="noopener external">{{ __('→ Hoe we beslissen') }}</a>
            <a href="https://github.com/cloudmarktplaats/cloudmarktplaats" class="hover:text-cmp-blue" rel="noopener external">{{ __('→ Code op GitHub') }}</a>
        </div>
```

- [ ] **Step 5: Voeg de Engelse vertaling toe**

In `lang/en.json`, direct onder `"→ Over ons": "→ About us",`:

```json
    "→ Hoe we beslissen": "→ How we decide",
```

- [ ] **Step 6: Draai de tests en zie ze slagen**

Run: `docker compose exec -T php-fpm ./vendor/bin/pest tests/Feature/Pages/ValuesPageTest.php`

Expected: alle vier de tests PASS.

- [ ] **Step 7: Controleer de vertaling**

Run:

```bash
docker compose exec -T php-fpm php artisan tinker --execute="app()->setLocale('en'); echo __('→ Hoe we beslissen');"
```

Expected: `→ How we decide`

- [ ] **Step 8: Commit**

```bash
git add docs/GOVERNANCE.md resources/views/pages/values.blade.php lang/en.json tests/Feature/Pages/ValuesPageTest.php
git commit -m "docs: GOVERNANCE.md — consultatie, geen stemrecht

De waardenpagina belooft nu dat keuzes openbaar voorgelegd worden. Dit is
waar dat staat: wie beslist, waarover niet, en waar het gebeurt.

De founding-badge krijgt geen zeggenschap: hij zegt wanneer je binnenkwam,
niet wat je bijdraagt.

Bugs, privacy, veiligheid en eerlijkheid gaan nooit de deur uit. Dat is
werk, geen mening."
```

---

### Task 3: Uitrol

**Achtergrond voor de implementer:** deployen is hier een file-sync naar LXC 214, géén `git pull`. Er is geen migratie, geen nieuwe config-sleutel en geen nieuwe asset — alleen een blade, een vertaling en een markdown-bestand. Dit is de goedkoopste uitrol van de week; de enige valkuil is de gecompileerde view.

**Files:**
- Geen wijzigingen. Dit is een uitrol van Taak 1 en 2.

**Interfaces:**
- Consumes: de commits uit Taak 1 en 2.
- Produces: niets.

- [ ] **Step 1: Draai de volle suite en de kwaliteitspoorten**

```bash
docker compose exec -T php-fpm ./vendor/bin/pest
docker compose exec -T php-fpm ./vendor/bin/pint --test
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G
```

Expected: alles groen. Klaagt Pint, draai `docker compose exec -T php-fpm ./vendor/bin/pint` en commit de opmaak apart.

- [ ] **Step 2: Sync naar productie**

Chown alleen de gesynchroniseerde bestanden, nooit een bovenliggende map: zo verloor `bootstrap/cache` eerder zijn www-data-eigenaarschap, waarna `config:cache` stil faalde terwijl de site een weken oude config bleef serveren.

```bash
cd /mnt/nvme1tb/projects/cloudmarktplaats
tar czf - \
  resources/views/pages/values.blade.php \
  lang/en.json \
  docs/GOVERNANCE.md \
| ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && tar xzf - && chown 1000:1000 resources/views/pages/values.blade.php lang/en.json docs/GOVERNANCE.md && echo synced'"
```

Expected: `synced`.

- [ ] **Step 3: Wis de gecompileerde view en herstart**

De blade is gewijzigd, dus de gecompileerde view moet weg — anders serveert prod de oude tekst en lijkt de uitrol mislukt. Draai artisan als `www-data`: als root maakt het `storage/logs` root-eigendom, waarna de web-worker 500's gooit die nergens gelogd worden.

```bash
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml exec -T -u www-data php-fpm php artisan view:clear'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart php-fpm'"
ssh root@192.168.178.88 "pct exec 214 -- bash -lc 'cd /opt/cloudmarktplaats && docker compose -f docker-compose.prod.yml restart nginx'"
```

Herstart nginx ná php-fpm, anders 502't de site.

- [ ] **Step 4: Verifieer wat er publiek staat**

```bash
sleep 3
echo -n "  waarden: "; curl -s -o /dev/null -w "%{http_code}\n" https://cloudmarktplaats.nl/waarden
echo "  nieuwe tekst:"; curl -s https://cloudmarktplaats.nl/waarden | grep -o "Eigenaarschap is hier het recht om weg te lopen met alles[^<]*"
echo "  link:"; curl -s https://cloudmarktplaats.nl/waarden | grep -o "Hoe we beslissen"
echo "  oude tekst weg?"; curl -s https://cloudmarktplaats.nl/waarden | grep -c "Geen aandeelhouders, geen exit, geen overname\.<"
echo -n "  healthz: "; curl -s -o /dev/null -w "%{http_code}\n" https://cloudmarktplaats.nl/healthz
```

Expected: `waarden: 200`; de nieuwe zin verschijnt; `Hoe we beslissen` verschijnt; `oude tekst weg?` geeft `0`; `healthz: 200`.

Verschijnt de nieuwe zin niet, dan draait prod op een gecachete view — herhaal Stap 3.

- [ ] **Step 5: Controleer de link met eigen ogen**

Open `https://cloudmarktplaats.nl/waarden`, klik op "→ Hoe we beslissen", en controleer dat je op `GOVERNANCE.md` op GitHub landt en dat het document leesbaar is. Een dode link naar een governance-document is erger dan geen link: dan belooft de pagina openheid en levert een 404.

Meld in je rapport wat je zag.

---

## Rollback

`git revert` van de twee commits en opnieuw syncen, gevolgd door `view:clear` en een herstart. Er is geen migratie, geen config-sleutel en geen asset; de oude tekst komt terug zoals hij was. `docs/GOVERNANCE.md` mag blijven staan — een document dat niemand aanwijst doet geen kwaad.
