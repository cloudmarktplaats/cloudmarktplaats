# DAC7 — Onze juridische positie

> *Stand: 2026-05-16, Foundation release `v0.1.0-foundation`. Dit document is een uitleg, geen juridisch advies; bij twijfel raadpleeg een fiscalist.*

## 1. Wat DAC7 is

DAC7 is een Europese richtlijn (Council Directive 2021/514) die digitale platforms verplicht om verkopers met een bepaalde omvang automatisch te rapporteren aan hun nationale belastingdienst. In Nederland is dat geïmplementeerd in de Wet op de internationale bijstandsverlening bij de heffing van belastingen (WIB), artikelen 10c–10n.

De rapportageplicht treedt in werking zodra een verkoper in een kalenderjaar:

- **30 of meer transacties** op het platform realiseert, **of**
- **€2.000 of meer** totale omzet op het platform haalt.

Eens een van beide drempels bereikt is, moet het platform NAW, BSN/btw-nummer, IBAN en transactiebedragen aanleveren bij de Belastingdienst (Form DAC7-XML), uiterlijk 31 januari van het volgende jaar.

## 2. Waarom DAC7 Foundation **niet** treft

Het hele juridische gebouw rust op één begrip: **een door het platform gefaciliteerde transactie**. In de tekst van DAC7 (artikel 8ac(2) van Richtlijn 2011/16/EU, zoals gewijzigd) is een rapporteerbare transactie er een waarbij:

> *"de betaling van de tegenprestatie geheel of gedeeltelijk via het platform plaatsvindt of het platform deze betaling kent of redelijkerwijs zou moeten kennen."*

Cloudmarktplaats Foundation **faciliteert geen betalingen op-platform**. De surface is:

1. Verkoper plaatst advertentie (`listings`-tabel).
2. Koper klikt "Neem contact op" — wordt naar login geredirect, daarna naar een notice ("messaging komt in een toekomstige sub-project").
3. Verkoper en koper handelen offline af: ze ontmoeten elkaar bij de afhaaladres, doen Tikkie, contant, IBAN-overboeking buiten ons gezichtsveld.

**Wij kennen de uitkomst niet en hebben er geen toegang toe.** Er is geen on-platform escrow, geen Stripe-Connect-account, geen Mollie-flow. De `transactions`-tabel staat al in de migratie — maar als geheugen voor wanneer een latere sub-project (#7 Web3-escrow, of #5 sponsoring/donations) wel transacties on-platform introduceert. Tot dat moment bevat de tabel structureel nul rijen voor reguliere koop/verkoop.

Dat brengt ons in dezelfde juridische categorie als bijvoorbeeld Tweakers' Vraag & Aanbod of subreddits als r/hardwareswap: een **advertentie-bord**, geen marktplaats-met-afrekening.

## 3. Wanneer rapportage wél begint

De rapportageplicht activeert op het moment dat een van deze sub-projecten live gaat met `feature flag = true` voor échte gebruikers:

| Sub-project | Trigger |
|---|---|
| **#5 Sponsoring / donaties** | Donaties via Stripe gaan via een Cloudmarktplaats-account, dus dat is een platformbetaling. Maar: donaties zijn geen transacties tussen verkoper en koper, dus DAC7 raakt dit alleen als wij **service fees** introduceren bovenop reguliere verkopen. Niet geplanned voor #5. |
| **#7 Web3 / escrow** | Escrow via een smart contract met onze adres in het pad **is** een door het platform gefaciliteerde transactie. Vanaf dat moment vullen we `transactions` rijen op iedere geslaagde swap, en kicked sub-project **#6 DAC7-module** in om jaarlijks een Belastingdienst-XML te exporteren. |

Het schema is dus **al ready** voor DAC7 (`transactions.platform_facilitated`, `transactions.completed_at`, `users.bsn_hash` voorzien) zodat de DAC7-module straks geen migratie-feestje hoeft te zijn. We rapporteren echter pas zodra er iets te rapporteren is.

## 4. Wat dit betekent voor verkopers nu

Een verkoper op Cloudmarktplaats Foundation moet **zelf** uitzoeken of zijn off-platform transacties belastbaar zijn (afhankelijk van: hobby-verkoper vs. ondernemer, omzetdrempels voor btw-vrijstelling, etc.). Wij geven geen 1099/jaaroverzicht. We adviseren: bewaar je eigen administratie en raadpleeg bij twijfel een fiscalist.

Op het account bij ons hebben we **bewust** geen BSN/btw-nummer-veld; we hebben die data niet nodig en kunnen er — gezien onze data-minimalisatie — ook niet de verantwoordelijkheid voor dragen.

## 5. Onze positie publiekelijk

Dit document is de single source of truth. Als een journalist, lezer of overheidsinstantie ons hierover bevraagt: dit is wat we zeggen, met links naar de relevante richtlijn-artikelen.

- Council Directive 2021/514: https://eur-lex.europa.eu/eli/dir/2021/514/oj
- Belastingdienst — DAC7 voor platforms: https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/internationaal/dac7-meldingsplicht
- WIB artikelen 10c–10n: https://wetten.overheid.nl/BWBR0040643
