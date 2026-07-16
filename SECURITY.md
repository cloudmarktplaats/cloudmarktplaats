# Beveiliging

## Een kwetsbaarheid melden

Meld beveiligingsproblemen **niet** als openbaar GitHub-issue. Een openbaar issue geeft iedereen die meeleest een handleiding voordat het gedicht is.

Mail in plaats daarvan **privacy@cloudmarktplaats.nl**. Beschrijf zo precies als je kunt:

- wat het probleem is en waar je het vond (URL, endpoint, scherm);
- hoe je het reproduceert;
- wat een kwaadwillende ermee zou kunnen.

Je krijgt binnen een paar dagen antwoord. We lossen het op, en pas als het gedicht is maken we het (als het relevant is) openbaar — met dank aan jou, tenzij je liever anoniem blijft.

## Wat wél en niet een kwetsbaarheid is

Handig om vooraf te weten, zodat je geen tijd verliest:

- **De IBAN op de doneerpagina is openbaar met opzet.** Zo ontvangen we donaties via bankoverschrijving. Met alleen een IBAN kan niemand geld van een rekening halen — een IBAN dient om geld te *sturen*. Dit is geen kwetsbaarheid.
- **Wél melden:** alles waarmee je toegang krijgt tot een account dat niet van jou is, gegevens van een ander kunt inzien of wijzigen, de anonimiteit van een homelab-plaatser kunt doorbreken, of code kunt draaien die er niet hoort.

## Wat we zelf al doen

- Geen trackers, geen third-party scripts — minder aanvalsoppervlak, minder data om te lekken.
- IP-adressen worden binnen 24 uur gewist.
- EXIF (inclusief GPS) wordt uit elke geüploade foto gestript vóór publicatie.
- De homelab-feed koppelt een plaatsing nooit publiek aan een account.

De code is open (AGPL-3.0). Kijk gerust mee — dat is precies de bedoeling.
