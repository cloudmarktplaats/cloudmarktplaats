# Gamification & trust — design (voorstel)

**Datum:** 2026-07-03
**Status:** VOORSTEL — wacht op Nick's richtingskeuzes (zie "Open beslissingen"). Defaults hieronder zijn mijn aanbeveling, niet vastgelegd.

## Leidend principe

**Beloon bijdrage en vertrouwen, niet populariteit.** Alles wat mensen tegen elkaar afzet
(publieke downvotes, ranglijsten, publieke negatieve scores) laten we weg. Alles wat
individuele trots of de gedeelde missie voedt (privé-stats, verdiende badges, trust-levels,
een coöperatieve e-waste-teller) bouwen we uit. Past bij de privacy-/anti-dark-pattern-ethos
van het platform en bij de nerd-doelgroep.

## Wat wel, wat niet (de anti-toxiciteits-analyse)

| Mechaniek | Oordeel | Waarom |
|---|---|---|
| Invite-codes → karma | **Bouwen** | Uitnodigingsboom = herleidbaarheid + kwaliteitsfilter. Karma pas bij actieve invitee, niet bij versturen. |
| Upvote-only waardering (op homelab-posts) | **Bouwen** | Appreciatie, geen oordeel. Geen downvote → geen pile-on. |
| Transactie-feedback ("ging goed") | **Bouwen** | Vertrouwen i.p.v. populariteit; gebonden aan echte 1-op-1-interactie, niet te brigaden. |
| Trust-levels (new→member→trusted→veteran) | **Bouwen** | Ontgrendelt capabilities (meer invites, moderatie-skip). Vermindert moderatielast. |
| Privé "stats voor nerds" dashboard | **Bouwen** | Niet-toxisch per definitie; sterkste fit met doelgroep + datasheet-design. |
| Badges/achievements | **Bouwen** | Verdiend, niet vergeleken. |
| Coöperatieve "gered van de sloop"-teller | **Bouwen** | Eén gedeeld getal; samenwerken = tegenovergestelde van toxisch; duurzaamheidshoek. |
| Publieke downvotes op mensen/posts | **SCHRAPPEN** | Dé bron van toxiciteit: pile-ons, score-angst, brigading. |
| Globale karma-leaderboard | **SCHRAPPEN** | Statusgames, gaming, ontmoedigt nieuwkomers. |
| Publieke negatieve reputatie-score | **SCHRAPPEN** | Harassment-vector. |

## Karma — hoe het werkt

Karma is primair een *capability-ontgrendelaar* + privé-signaal, geen publiek getal.

Verdien karma door:
- Een invitee die zijn eerste trade afrondt (niet: enkel een code versturen).
- Positieve transactie-feedback ontvangen.
- Upvotes op je homelab-posts.
- Mijlpaal-badges.

Karma ontgrendelt:
- Meer invite-codes (start met 0–3, groeit met trust-level).
- Trust-levels; "trusted" laat je advertenties de pre-moderatie-wachtrij overslaan.
- Later: toegang tot meer categorieën / features.

Anti-misbruik: karma-mutaties gelogd (audit). Inviter deelt in reputatieschade als invitee
wordt gebanned (skin in the game). Rate-limits op feedback/upvotes. Feedback alleen ná een
echt gerelayd contact.

## Gefaseerde bouw

- **Fase 1 — Invites + karma-basis:** `invite_codes` + `karma_events` (append-only ledger,
  karma = som), invite-boom (`users.invited_by`), UI om codes te genereren/inwisselen bij
  registratie, karma-toekenning wanneer invitee eerste trade afrondt. Fundament voor de rest.
- **Fase 2 — Stats-dashboard + badges:** privé `/profile/stats` in monitoring-stijl; badge-engine
  (afgeleide achievements); coöperatieve e-waste-teller op een publieke pagina.
- **Fase 3 — Transactie-feedback + trust-levels:** deal-bevestiging na contact; trust-level-engine
  die moderatie-skip en invite-quota ontgrendelt.
- **Fase 4 — Upvote-waardering:** upvote-only op homelab-posts, gevoed terug in karma.

Elke fase is los bruikbaar en krijgt een eigen implementatieplan.

## Fase 1 in detail (invites + karma)

**Data:**
- `invite_codes`: id, code (uniek), inviter_user_id, invitee_user_id (nullable), used_at,
  expires_at, revoked_at.
- `karma_events`: id, user_id, type (enum: invite_activated, feedback_positive, post_upvoted,
  badge_earned), points, source_type/source_id (polymorf), created_at. Karma = SUM(points).
- `users.invited_by` (nullable fk), `users.invite_credits` (int, hoeveel codes ze mogen maken).

**Flows:**
- Genereren: een gebruiker met invite_credits > 0 maakt een code; credit -1.
- Inwisselen: bij registratie kan een code worden opgegeven → `invited_by` gezet, code gekoppeld.
  (Open registratie blijft mogelijk; een code geeft alleen een voorsprong — zie open beslissing.)
- Karma: wanneer de invitee zijn eerste listing gepubliceerd krijgt (of eerste trade —
  zie open beslissing), krijgt de inviter `invite_activated` karma.
- Ban-terugslag: bij ban van een invitee wordt de `invite_activated`-karma teruggeboekt.

**Privacy:** de invite-boom is intern (Filament/eigen dashboard), nooit publiek. Geen publieke
"uitgenodigd door X".

## Open beslissingen (Nick)

1. **Publieke downvotes:** schrappen (aanbevolen) / upvote+downvote op posts / alleen feedback.
2. **Fase 1 startpunt:** invites+karma (aanbevolen) / stats-dashboard / feedback+trust-levels.
3. **Registratiemodel:** open + invites-voor-voorsprong (aanbevolen) / invite-only / open-zonder-invites.
4. **Karma-trigger voor inviter:** invitee's eerste *gepubliceerde advertentie* (eenvoudig, nu
   meetbaar) of eerste *afgeronde trade* (sterker signaal, vereist eerst het trade-/feedback-systeem).
