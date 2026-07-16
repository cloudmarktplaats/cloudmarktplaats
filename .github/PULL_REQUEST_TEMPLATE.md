<!--
Dank voor je bijdrage! Houd het kort — dit sjabloon is een geheugensteun,
geen formulier dat je verplicht helemaal invult.
Zie CONTRIBUTING.md voor het volledige verhaal.
-->

## Wat & waarom

<!-- Wat lost dit op? Het waarom, niet alleen het wat — de diff laat het wat al zien.
     Verwijs naar het issue als dat er is: "Closes #123". -->

## Poorten (draai lokaal vóór je pusht)

- [ ] `./vendor/bin/pest` — groen (nieuwe code/bugfix heeft een test die zonder de fix faalt)
- [ ] `./vendor/bin/pint --test` — groen
- [ ] `./vendor/bin/phpstan analyse --memory-limit=1G` — groen (level 8)

## Even nalopen

- [ ] Eén ding per PR — geen losse "while I'm here"-opschoning erdoorheen.
- [ ] Zichtbare teksten zijn NL, met een EN-vertaling in `lang/en.json` (geldige JSON, geen dubbele sleutels).
- [ ] Raakt dit privacy, de anonimiteit van de homelab-feed, of tracking? Zo ja: leg hieronder uit waarom het klopt.

<!-- Je bijdrage valt onder AGPL-3.0, net als de rest van de codebase. -->
