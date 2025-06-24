# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Cloudmarkplaats.nl is a PHP-based marketplace platform for IT hardware trading. It uses a custom MVC architecture with MySQL database, Bootstrap 5 for UI, and HTMX for dynamic updates.

## Essential Commands

### Setup & Development
```bash
# Install dependencies
composer install

# Import database schema
mysql -u root -p cloudmarkplaats1 < database.sql

# Run local development server
php -S localhost:8000
```

### Database Updates
```bash
# Apply database migrations (manual process)
mysql -u root -p cloudmarkplaats1 < update_messages.sql
```

## Architecture & Code Organization

### MVC Structure
- **Controllers** (`/controllers/`): Handle HTTP requests and business logic
  - `AuthController.php`: User authentication and registration
  - `ProductController.php`: Product CRUD operations
  - `ForumController.php`: Forum functionality
  - `MessageController.php`: User messaging system
- **Views** (`/views/`): PHP templates organized by feature
  - `/auth/`: Login, register, password reset
  - `/product/`: Product listings and details
  - `/dashboard/`: User dashboard views
  - `/forum/`: Forum categories and topics
- **Models**: Database interactions via PDO in `/includes/Database.php`

### Key Components
1. **Database Connection**: `/includes/Database.php` - PDO wrapper for MySQL
2. **Configuration**: `/config.php` - App settings (needs refactoring to use .env)
3. **Session Management**: `/includes/session.php` - User session handling
4. **Router**: Apache mod_rewrite via `.htaccess` redirects to `index.php`

### Database Schema
- **users**: User accounts with role-based access (user/admin)
- **products**: Product listings with approval workflow
- **messages**: User-to-user messaging
- **forum_categories/topics/replies**: Forum structure
- **reviews**: Product reviews and ratings
- **favorites**: User wishlists

### Important Patterns
1. **Authentication**: Session-based with role checking
2. **File Uploads**: Product images stored in `/uploads/products/`
3. **URL Routing**: All requests route through `index.php` via .htaccess
4. **Database Queries**: Direct PDO usage, no ORM

## Development Notes

### Current Limitations
- No automated testing framework
- Database credentials hardcoded in `config.php`
- No code linting or formatting tools configured
- Manual database migrations

### Security Considerations
- Always validate user input before database operations
- Use prepared statements for all database queries (already implemented)
- Check user permissions before sensitive operations
- File upload validation is critical for product images

### Common Tasks
1. **Adding a new page**: Create controller method, add view file, update navigation
2. **Database changes**: Update `database.sql` and create migration file
3. **Adding features**: Follow existing MVC pattern in controllers/views
4. **Debugging**: Error reporting enabled in `config.php` for development

### Git Commit Guidelines
- Never include "Co-Authored-By: Claude" in commit messages
- Never mention "Claude Code" or "Generated with Claude Code" in commits
- Write clean, professional commit messages focusing on the changes made