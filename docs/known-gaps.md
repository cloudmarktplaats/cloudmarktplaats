# Bekende gaten — Foundation `v0.1.0-foundation`

Dit document benoemt expliciet wat in Foundation **nog niet** opgelost is. Iets staat hier als:

- We weten dat het bestaat.
- We hebben er bewust voor gekozen het uit Foundation te houden (scope), **of** we hebben het ontdekt tijdens implementatie en de echte fix in een later sub-project gesequenced.
- Iemand op vrijdagmiddag een crash-rapport krijgt en wil weten of het bekend is.

> Werkbare bugs / feature-verzoeken die hier niet in staan: open een [GitHub Issue](https://github.com/cloudmarktplaats/cloudmarktplaats/issues).

---

## Lockout & recovery

### Total-lockout recovery is handmatig
Een gebruiker die **én** 2FA-token kwijt is, **én** geen recovery-codes meer heeft, **én** geen toegang tot het oorspronkelijke e-mailadres, kan op dit moment niet automatisch zijn account terugkrijgen. De `IdentityService` weigert terecht zo'n combinatie automatisch te verwerken (anders is het een trivial bypass-vector). Tot er een dedicated support-sub-project draait moet zo'n geval via een GitHub Issue + handmatige admin-actie. Filament's `Force-disable 2FA` is daarvoor de tool, en alle uitgevoerde force-disables worden in `admin_actions` gelogd.

### Geen "impersonate user" admin
Bewuste keuze: we hebben geen "log in als deze gebruiker"-knop in het admin-panel. Dat zou een fraude- en privacy-risico zijn dat we niet kunnen kwantificeren, zonder daar een aparte consent-flow voor te bouwen.

---

## Listings & foto's

### Foto-cleanup hook ontbreekt
Wanneer een advertentie wordt verwijderd (soft-delete of hard-delete) blijven de foto-blobs in storage achter. De variant-paden (`listings/{ulid}/{photo_id}/original.jpg`, etc.) zijn nu **wel** mee-soft-deleted in de `listing_photos`-tabel, maar er is nog geen cron die de feitelijke blobs opruimt. Pickup-werk voor sub-project **#4 Reviews** (waar we toch al cleanup-cron toevoegen voor verwijderde reviews) of een dedicated klein "storage gc" PR.

### Wizard draft auto-resume UX
De wizard *bewaart* drafts correct (per stap → DB). Maar er is nog geen "drafts"-tab in het dashboard die de open drafts toont. Een gebruiker moet zijn eigen URL onthouden (`/listings/{ulid}/edit`) om verder te gaan. Een dashboard-widget komt mee met sub-project #2 (Messaging) waar we toch al een persoonlijk dashboard uitbouwen.

### Listing slug uniqueness scope
`listings.slug` heeft een unique-constraint scoped per `(user_id, slug)`, niet globaal. Dat is goed (anders kun je geen "iMac G3" twee keer adverteren tussen verschillende verkopers), maar betekent dat de detail-URL `'/listings/{ulid}-{slug}'` slug-collisions kan bevatten *across* verkopers. De ulid is uniek dus dit veroorzaakt geen 404, en het slug-canonicalization redirect (zie §G5-polish) zorgt dat search engines er geen ruzie over krijgen.

---

## Auth duplicatie

### `postLogin` boilerplate in 4 controllers
`Login`, `OAuthController`, `Web3Controller` en `TwoFactorChallenge` doen alle vier identieke huishouding na een geslaagde primary-auth: regenereer session-id, update `last_login_at` + `last_login_ip`, controleer 2FA-eis, redirect target. Tijdens Phase F is hier gemerkt dat dit een geschikt extract-target is (`SessionService::completeLogin($user)` of een `LogsInUser` trait), maar de bonus-refactor in Phase I is **bewust niet uitgevoerd**. Reden: alle vier de callers hebben subtiel verschillende redirect-defaults (Login → `/`, OAuth → terug-url-uit-session, SIWE-nieuwe-wallet → `/auth/web3/onboarding/...`, 2FA → `/`) en het extract zou een vlaggen-parameter-hel worden zonder dat de duplicatie pijn doet. Pickup-werk zodra er een vijfde caller bijkomt (b.v. magic-link-login).

### `LegalAcceptance` middleware niet op login-route
De middleware wordt **alleen** toegepast op routes met juridisch gevolg (wizard). Een gebruiker met stale acceptance kan dus gewoon inloggen, browsen, instellingen aanpassen — pas bij het plaatsen van een nieuwe advertentie wordt er om re-acceptatie gevraagd. Dat is een bewuste keuze (niet iedereen op alle pagina's lastig vallen) maar betekent ook: oude stale-acceptance gebruikers zien nooit een prompt totdat ze iets willen plaatsen. Als die UX te subtiel blijkt komt er een banner op `/` (out-of-scope voor Foundation).

---

## Tests

### E2E walkthrough via HTTP, niet via Pest Browser
De §14 acceptatie-walkthrough is geïmplementeerd als HTTP-level feature-test (`tests/Feature/Acceptance/AcceptanceWalkthroughTest.php`) in plaats van een echte Playwright-driven browser-test. Reden: Pest Browser + Livewire 3 + Filament 3 + onze huidige docker-compose-setup gaf te fragiele resultaten in CI (snapshot-validation-issues, race conditions tijdens form-submit). De HTTP-test dekt alle waarneembare effecten (state-transitions, DB-rijen, redirect-targets) maar test geen JS-rendering. Pickup-werk voor een latere CI-modernisatie zodra we Playwright fatsoenlijk in compose gehost krijgen.

### Geen browser-test voor Filament-paneel
De Filament-tests draaien via `Livewire::test(...)`-helpers (geen browser). Voor Foundation voldoende; Filament's eigen test-suite dekt de UI-laag.

---

## Privacy & retentie

### Logbestanden bevatten IPs langer dan 24 uur
De `IpStripperJob` wist `users.last_login_ip` na 24 uur, maar Nginx/PHP-FPM access-logs bevatten request-IPs voor de duur die in `docker/nginx/*.conf` is geconfigureerd (default: rotated bij 100 MiB, max 5 files, geschat ~7 dagen). Dat is voor incident-response prima, maar formeel een gat in de "we bewaren IPs maximaal 24 uur"-belofte. Fix: log rotation tunen of `set_real_ip_from` + obfuscatie. Pickup-werk voor het ops-document.

---

## Wat staat hier opzettelijk **niet** in

Niet alles wat we niet hebben gebouwd is een "gap". Specifiek:

- **Geen reviews / ratings** — bewuste keuze van sub-project #4 (geen reputation zonder dispute resolution).
- **Geen messaging** — sub-project #2.
- **Geen on-platform betalen / escrow** — sub-project #5 (donaties) en #7 (Web3-escrow).
- **Geen mobile app / native client** — out of scope; PWA-werk komt later.

Die staan in de roadmap (`docs/superpowers/specs/2026-05-16-cloudmarktplaats-v2-foundation-design.md` §12), niet hier.
