# Cloudmarktplaats DESIGN.md

> Design system voor cloudmarktplaats.nl — peer-to-peer marktplaats voor gebruikte IT-hardware.
> Richting: **"datasheet, geen startup."** De doelgroep (homelabbers, sysadmins, tweakers) leest
> spec-sheets voor z'n plezier en wantrouwt marketing. Vertrouwen komt van dichtheid, specs,
> foto's en eerlijke conditie-informatie — niet van whitespace-hero's en dark-mode-gradients.

## Referentiewereld

Datacenter-surplus als fysieke wereld: vendor-datasheets (IBM/Dell/Supermicro), thermal-printed
inventarisstickers op pallets, hot-swap-tabs, kabelmanagement. Géén: SaaS-landingspagina's,
dark-mode devtools, glassmorphism, gradients.

## Kleuren

Licht en koel-neutraal — datasheets zijn wit. Eén accent: safety-orange, de kleur van
hot-swap-tabs en warning-labels op echte hardware.

| Token | Hex | Gebruik |
|---|---|---|
| `cmp-bg` | `#F5F6F6` | Paginaachtergrond (koel, géén crème) |
| `cmp-bg2` | `#EDEFEF` | Wells, foto-placeholders |
| `cmp-bg3` | `#E4E7E7` | Diepere wells |
| `cmp-surface` | `#FFFFFF` | Kaarten, panelen, sticker |
| `cmp-border` | `#D9DDDE` | Hairlines (1px) |
| `cmp-ink` / `cmp-text` | `#17191B` | Tekst, sticker-kader, primaire knoppen |
| `cmp-muted` | `#5A6167` | Secundaire tekst |
| `cmp-faint` | `#9AA1A6` | Tertiair, placeholders |
| `cmp-signal` | `#D9480F` | HET accent: CTA's, section-label-ticks, actieve staat |
| `cmp-blue` | `#1447CC` | Alleen links in lopende tekst |
| `cmp-amber` | `#B45309` | Waarschuwingen (conditie defect) |

Regels: safety-orange is het enige accent dat aandacht mag vragen. Blauw is gedegradeerd tot
linkkleur. Nooit orange + blauw naast elkaar in één component. Grote vlakken blijven wit/neutraal.

## Typografie

Eén superfamilie: **IBM Plex** — inhoudelijk raak (IBM ís enterprise-hardware-erfgoed) en
coherent over drie rollen. Self-hosted woff2, latin subset, geen Google Fonts (privacy).

| Rol | Face | Gewichten | Gebruik |
|---|---|---|---|
| Display | IBM Plex Sans Condensed | 600, 700 | Koppen. Condensed = datasheet-energie. Tracking -0.01em. |
| Body | IBM Plex Sans | 400, 500, 600 | Lopende tekst, UI. 15px basis, line-height 1.65. |
| Data | IBM Plex Mono | 400, 500 | Prijzen, specs, postcodes, labels, de sticker. |

Regel: **alles wat data is, staat in mono** — prijzen, condities, datums, regio's, versienummers.
Alles wat proza is, staat in sans. Koppen condensed. Geen andere fonts.

## Signature-element: de inventarissticker

Elke advertentie draagt een thermal-label-achtige inventarissticker (component
`<x-inventory-label>`): wit vlak, 2px massief ink-kader, **géén** border-radius, mono uppercase,
een barcode-strip van CSS-strepen. Rijen als `CONDITIE`, `REGIO`, `GEPLAATST`. De conditiewaarde
staat geïnverteerd (ink-vlak, witte tekst). Dit is geen decoratie: conditie-grading is de echte
informatie waar kopers op selecteren.

- Detailpagina: volledige sticker naast de prijs.
- Kaarten in het grid: mini-variant (alleen conditieblokje in sticker-stijl).
- Homepage-hero: één oversized sticker als visueel anker (met de privacy-specs als rijen).

## Componenten

- **Knoppen**: rechthoekig (`rounded-sm`, 2px). Primair: ink-vlak, witte tekst, hover →
  safety-orange. Secundair: 1px ink-border, transparant. Geen schaduwen, geen gradients.
- **Kaarten**: wit, 1px `cmp-border`, `rounded-sm`, hover → border wordt ink. Layout als
  spec-regel: foto (4:3), titel, mono-prijs rechts uitgelijnd, mono-regio, conditie-mini-label.
- **Section-labels**: mono uppercase 11px ink, met een oranje tick (16×2px vlak) ervoor.
- **Tabellen boven cards**: USP's en features als datasheet-rijen (dt/dd, hairline tussen rijen),
  niet als icoon-kaartjes-grid.
- **Formulieren**: 1px border, `rounded-sm`, focus-ring orange. Labels in sans 500, hints in mono.

## Layout

- Max-breedte 72rem, gutters 20/32px. Dichtheid boven lucht: secties 3–4rem verticaal, niet 6+.
- **De marktplaats ís de homepage**: recent aanbod staat direct onder de hero-vouw.
- Hero links uitgelijnd (geen centreer-theater): kop + subregel + CTA's links, de
  oversized inventarissticker rechts.
- Structuurmiddelen coderen informatie: geen 01/02/03-nummering (er is geen volgorde),
  wel spec-rijen en labels.

## Beweging

Vrijwel geen. Snelheid ís het respect dat deze doelgroep verwacht. Toegestaan: kleur/border op
hover (150ms), focus-ringen. Verboden: tagline-carrousels, scroll-reveals, parallax,
image-zoom-on-hover. `prefers-reduced-motion` wordt al gerespecteerd; er valt weinig te reduceren.

## Stem

Nederlands, droog, technisch eerlijk. Specs in plaats van adjectieven. "IP-adressen gewist na
24 uur" in plaats van "wij geven om je privacy". Zelfspot mag ("geen cookiebanner-theater"),
verkooppraat niet. Data altijd concreet: getallen, datums, versies.

## Wat dit systeem expliciet níet is

Donkere achtergrond + acid-green + Space Grotesk + genummerde feature-kaartjes — het
AI-standaardrecept waar dit systeem van weg ontworpen is. Bij twijfel: zou dit op een
Supermicro-datasheet of een pallet-sticker kunnen staan? Zo nee, weglaten.
