# Cloudmarkplaats.nl (English)

> **Note**: This is an English translation of the README. The Dutch version (README.md) is the authoritative source and supersedes this document. Please refer to the original [README.md](README.md) for the most up-to-date information.

A platform for IT experts to trade hardware.

## Requirements

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer
- Apache/Nginx web server
- XAMPP (for local development)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/cloudmarkplaats.git
cd cloudmarkplaats
```

2. Install dependencies:
```bash
composer install
```

3. Copy the .env.example file to .env and adjust the configuration:
```bash
cp .env.example .env
```

4. Create a new MySQL database and import the schema:
```bash
mysql -u root -p
CREATE DATABASE cloudmarkplaats;
exit;
mysql -u root -p cloudmarkplaats < database.sql
```

5. Ensure the web server has write permissions for the following directories:
```bash
chmod -R 777 public/uploads
chmod -R 777 storage/logs
```

6. Start the web server and visit the website:
```
http://localhost/cloudmarkplaats
```

## Features

- User authentication with Web3/OAuth
- Product listings with tags and images
- Messaging system between users
- Reputation system with reviews
- Forum with categories
- RSS feed integration
- Wishlist functionality
- Search functionality with filters

## Technical Stack

- PHP 8.2
- MySQL 8.0
- PDO for database connections
- Bootstrap 5 for styling
- HTMX for dynamic updates
- Composer for dependency management

## Development

1. Create a new branch for your feature:
```bash
git checkout -b feature/new-feature
```

2. Commit your changes:
```bash
git add .
git commit -m "Description of changes"
```

3. Push to the remote repository:
```bash
git push origin feature/new-feature
```

## License

Copyright © Man of Solutions B.V. All rights reserved.

This is proprietary software. See the [LICENSE.md](LICENSE.md) file for details.