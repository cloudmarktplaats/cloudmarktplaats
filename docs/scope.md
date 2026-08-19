# Wat mag er op Cloudmarktplaats?

> **Status: concept, wacht op akkoord.** Bedoeld om gepubliceerd te worden op
> cloudmarktplaats.nl (FAQ + eigen pagina) en om te dienen als moderatierichtlijn.
> De categorieboom hieronder is niet nieuw bedacht: hij komt één-op-één uit
> `database/seeders/CategorySeeder.php` en draait al.

## Het uitgangspunt

**Als het thuishoort in een lab, een serverkast, een netwerk of op een werkbank, mag het erop.**

Dat is de hele regel. De rest van dit document is er alleen voor de randgevallen.

## Drie tests voor twijfelgevallen

1. **Categorietest** — past het in één van de twaalf categorieën hieronder? Zo ja: plaatsen.
2. **Doeltest** — koopt iemand dit om er iets *mee te bouwen, meten, draaien of aansluiten*,
   of om er *mee te consumeren*? Bouwen mag, consumeren niet. Een beamer voor een
   presentatieruimte: ja. Een tv voor de bank: nee.
3. **Ruistest** — is het los de moeite van een advertentie waard? Zo niet: bundel het.
   Eén patchkabel is ruis. Een doos van veertig is een kavel.

Twijfel je na drie tests nog? Plaats het. Wij halen het er desnoods af — dat kost jou
minder dan dat het platform leeg blijft.

## De categorieën (draait al)

| Categorie | Waar het over gaat |
|---|---|
| **Server hardware** | Rack- en towerservers, blades en chassis, serveronderdelen, rails, racks |
| **Networking** | Switches, routers, firewalls en appliances, access points, NIC's, transceivers, VoIP |
| **Storage** | SSD, HDD, NVMe, NAS, SAN en disk shelves, RAID/HBA-controllers, tape |
| **Compute** | CPU, GPU, RAM, moederborden, koeling, behuizingen, barebones/mini-PC's, laptops, workstations, thin clients, dev boards/SBC, monitoren, KVM's, randapparatuur |
| **Kabels & connectoren** | Netwerk-, stroom-, data-/SAS-/SATA-kabels, adapters en converters |
| **Power** | Voedingen, UPS'en, PDU's |
| **Audio/Video pro** | Beamers, signage, mengpanelen en versterkers, microfoons, camera's/capture, encoders |
| **Meetapparatuur** | Oscilloscopen, multimeters, labvoedingen, logic analyzers, functiegeneratoren, soldeerstations |
| **3D printers & CNC** | FDM, resin, CNC en lasers, filament/resin, printeronderdelen |
| **Software licenties** | Alleen overdraagbaar, zie hieronder |
| **Boeken & documentatie** | Handboeken, cursusmateriaal, oude documentatie |
| **Overig** | Wat er echt niet in past maar wel in een lab hoort |

## Wat er niet op mag

- **Consumentenelektronica zonder lab-toepassing.** Spelcomputers en controllers,
  telefoons, tablets, smart-tv's, huishoudelijke apparatuur, scart- en tulpkabels.
- **Provider-apparatuur die je niet mag doorverkopen** (de modem/router van je ISP).
- **Apparatuur met data van anderen erop.** Zie de dataregel hieronder.
- **Namaak-transceivers en -onderdelen** die als origineel worden aangeboden.
- **Alles waarvan de verkoop verboden is,** en apparatuur waarvan je de herkomst niet kunt
  verantwoorden.
- **Diensten, advertenties en vacatures.** Dit is een marktplaats voor spullen.

## Grijze gevallen — hierbij de beslissing

| Geval | Oordeel | Waarom |
|---|---|---|
| Losse desktop-CPU (i3, i5) | **Ja** | `compute.cpu` bestaat. Iemands NAS of firewall begint hier. |
| Oude gaming-GPU | **Ja** | Draait nu inference of transcoding. Vermeld wel het verbruik. |
| 3D-printer aan het netwerk | **Ja** | Eigen categorie. |
| Vintage meetapparatuur, ook uit de jaren 70/80 | **Ja** | Werkbank telt. Leeftijd is geen diskwalificatie. |
| Losse HDMI- of patchkabel van €2 | **Alleen als kavel** | Ruistest. Bundel ze. |
| Laptop of monitor | **Ja** | Staat in de boom. Een thin client met een scherm is een lab-werkplek. |
| Toetsenbord/muis | **Ja, gebundeld** | Randapparatuur telt, los is het ruis. |
| Spelcomputer | **Nee** | Consumeren, niet bouwen. Ook als je er Linux op draaide. |
| Flipper Zero, RF- en pentest-hardware | **Ja** | Legaal bezit, lab-doel. Geen gebruiksinstructies in de advertentie. |
| Kavel: "doos met SFP's, doe een bod" | **Ja, aangemoedigd** | Verlaagt de drempel het meest. |
| Defect apparaat voor onderdelen | **Ja, mits "defect" in de titel** | Onderdelen zijn de helft van een lab. |

## Vier regels die geen scope zijn, maar wel gelden

1. **Data eraf, altijd.** Schijven, NAS'en, firewalls, switches en servers gaan gewist en
   fabrieksreset de deur uit. Zet in de advertentie hoe je het gewist hebt. Vind je een
   apparaat met data van een ander erop: meld het, plaats het niet.
2. **Beschrijf de staat eerlijk, inclusief het vervelende deel.** Dode UPS-accu, ontbrekende
   caddies, luidruchtige fans, verbruik in watt. De koper hier is een bouwer, geen impulskoper —
   die wil weten wat hij binnenhaalt. Opgepoetste refurb-praat werkt tegen je.
3. **Licenties alleen als ze overdraagbaar zijn.** OEM-Windows plakt aan het moederbord en
   verhuist niet mee. Verkoop de hardware, niet een licentie die de koper niet krijgt.
4. **Verkoop je bedrijfsmatig, zeg dat er dan bij.** Voor een MSP die uitfaseert gelden
   andere regels dan voor een particulier met een doos. *Openstaand punt: bepalen of en
   hoe we zakelijke verkopers apart labelen — consumentenrecht (garantie, herroeping) hangt
   hieraan.*

## Hoe deze lijst verandert

Via een issue op GitHub, in het openbaar. De eerste honderd leden bepalen de cultuur —
dat gold voor de toon en het geldt ook hier. Wijzigingen worden gedateerd in dit bestand,
niet stilletjes doorgevoerd.
