# Cloudmarkplaats.nl

[EN](README-EN.md) | NL

Een platform voor IT experts om hardware te verhandelen.

## Vereisten

- PHP 8.2 of hoger
- MySQL 8.0 of hoger
- Composer
- Apache/Nginx webserver
- XAMPP (voor lokale ontwikkeling)

## Installatie

1. Clone de repository:
```bash
git clone https://github.com/yourusername/cloudmarkplaats.git
cd cloudmarkplaats
```

2. Installeer dependencies:
```bash
composer install
```

3. Kopieer het .env.example bestand naar .env en pas de configuratie aan:
```bash
cp .env.example .env
```

4. Maak een nieuwe MySQL database aan en importeer het schema:
```bash
mysql -u root -p
CREATE DATABASE cloudmarkplaats;
exit;
mysql -u root -p cloudmarkplaats < database.sql
```

5. Zorg ervoor dat de webserver schrijfrechten heeft op de volgende mappen:
```bash
chmod -R 777 public/uploads
chmod -R 777 storage/logs
```

6. Start de webserver en bezoek de website:
```
http://localhost/cloudmarkplaats
```

## Features

- Gebruikersauthenticatie met Web3/OAuth
- Product listings met tags en afbeeldingen
- Messaging systeem tussen gebruikers
- Reputatiesysteem met beoordelingen
- Forum met categorieën
- RSS feed integratie
- Wishlist functionaliteit
- Zoekfunctionaliteit met filters

## Technische Stack

- PHP 8.2
- MySQL 8.0
- PDO voor database connecties
- Bootstrap 5 voor styling
- HTMX voor dynamische updates
- Composer voor dependency management

## Ontwikkeling

1. Maak een nieuwe branch voor je feature:
```bash
git checkout -b feature/nieuwe-feature
```

2. Commit je wijzigingen:
```bash
git add .
git commit -m "Beschrijving van de wijzigingen"
```

3. Push naar de remote repository:
```bash
git push origin feature/nieuwe-feature
```

## Licentie

Copyright © Man of Solutions B.V. Alle rechten voorbehouden.

Dit is propriëtaire software. Zie het [LICENSE.md](LICENSE.md) bestand voor details. 