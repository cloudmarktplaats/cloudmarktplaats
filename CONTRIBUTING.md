# Bijdragen aan Cloudmarktplaats

Cloudmarktplaats wordt door de community gebouwd — homelabbers, sysadmins, tweakers. Je hoeft geen professional te zijn om mee te doen. Een bugmelding, een idee, een testrondje of een pull request: alles telt.

Dit document is de wegwijzer. De README dekt de installatie; dit dekt *hoe* we samenwerken en waar de grenzen liggen.

## Wat we bouwen, en waar we niet aan tornen

Een paar dingen liggen vast en zijn geen smaakkwestie. Een PR die hiertegenin gaat wordt niet gemerged, hoe goed de code ook is:

- **Privacy is een ontwerpkeuze, geen instelling.** Geen trackers, geen Google Analytics, geen Facebook Pixel, geen third-party scripts. Data die we niet hebben, kan niet lekken. Bewaar het minimale.
- **De homelab-feed is anoniem.** Wie een homelab plaatst, wordt nooit publiek gekoppeld aan zijn account. Dat contract staat in de code (`HomelabPost`) en in de FAQ. Raak je code die dit aanraakt, dan hoort er een test bij die bewijst dat de identiteit niet lekt.
- **Geen algoritmische manipulatie.** Sorteren op datum, prijs, afstand. Geen "voor jou aanbevolen" dat eigenlijk "voor onze marge aanbevolen" is.
- **Datasheet, geen startup.** De stijl is dicht, feitelijk, IBM Plex, licht. Zie `docs/DESIGN.md`. Geen hero-gradients, geen marketingtaal.

Als je twijfelt of een idee hierin past: open eerst een issue en vraag het. Dat is sneller dan een afgewezen PR.

## Begin bij het probleem, niet bij de oplossing

Voor features werken we **spec-first**. Het waarom bepaalt wat er gebouwd wordt.

1. Open een [issue](https://github.com/cloudmarktplaats/cloudmarktplaats/issues) met het *probleem* — waar liep je tegenaan, wat wilde je dat niet kon. Niet met een kant-en-klare oplossing.
2. Grotere features krijgen een ontwerp in `docs/superpowers/specs/` voordat er code komt. Dat voorkomt weggegooid werk: we zijn het eerst eens over de bedoeling, dan pas over de bouw.
3. Kleine, duidelijke bugfixes mogen direct als PR — daar is geen spec voor nodig.

Wil je aan iets bestaands werken? Zeg het in het issue ("ik pak deze op"), dan werken we niet dubbel. Voor je eerste PR helpen we je op weg — vraag gerust.

## De code lokaal draaien

Volledige setup staat in de [README](README.md#3-quickstart). Kort:

```bash
docker compose up -d
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate --seed
npm install && npm run build
```

De app draait op `http://localhost:8080`, mail vangt Mailpit op `:8025`.

## De drie poorten die groen moeten blijven

Elke PR moet deze drie halen. Draai ze lokaal voordat je pusht — het scheelt een review-rondje:

```bash
docker compose exec -T php-fpm ./vendor/bin/pest                              # tests
docker compose exec -T php-fpm ./vendor/bin/pint --test                      # code style
docker compose exec -T php-fpm ./vendor/bin/phpstan analyse --memory-limit=1G # static analysis (level 8)
```

- **Pest:** nieuwe code hoort een test te hebben. Een bugfix hoort een test te hebben die zonder de fix faalt — dat is het bewijs dat hij de bug echt raakt.
- **Pint:** draai `./vendor/bin/pint` (zonder `--test`) om de opmaak automatisch recht te trekken.
- **PHPStan:** level 8. De `--memory-limit=1G` is nodig; zonder crasht hij.

Teksten die de gebruiker ziet zijn Nederlands, met een Engelse vertaling in `lang/en.json` (sleutel = de Nederlandse zin). Het bestand moet geldige JSON blijven, zonder dubbele sleutels.

## Pull requests

- Geen verplichte CLA. Je bijdrage valt onder dezelfde **AGPL-3.0** als de rest — publiek gedraaide wijzigingen blijven open.
- Houd een PR gefocust op één ding. Losse "while I'm here"-opschoning apart.
- Beschrijf *waarom*, niet alleen *wat*. De diff laat het wat al zien.
- Raakt je PR een van de vaste principes hierboven (privacy, anonimiteit, geen tracking)? Benoem het expliciet en leg uit waarom het klopt.

## Een beveiligingsprobleem gevonden?

**Niet als openbaar issue.** Zie [`SECURITY.md`](SECURITY.md) — mail het naar `privacy@cloudmarktplaats.nl`, dan lossen we het eerst op voordat het bekend wordt.

## Twijfel je?

Open gewoon een issue en vraag het. Een half idee is beter dan een niet-gesteld idee. De eerste 100 leden vormen de cultuur — dus ook jij.
