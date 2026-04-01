# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Cloudmarkplaats.nl is a PHP 8.1+ marketplace platform for IT hardware trading. It uses a PSR-4 namespaced MVC architecture with MySQL database, Bootstrap 5 for UI, and HTMX for dynamic updates.

## Essential Commands

### Setup & Development
```bash
# Install dependencies
composer install

# Copy environment config
cp .env.example .env  # then edit with your DB credentials

# Run database migrations
php migrations/migrate.php

# Run local development server
php -S localhost:8000

# Run tests
./vendor/bin/phpunit
```

## Architecture & Code Organization

### PSR-4 MVC Structure (`src/`)

- **Core** (`src/Core/`): Framework components
  - `App.php`: Application bootstrap, middleware pipeline, request dispatch
  - `Config.php`: Environment-based configuration via .env
  - `Database.php`: Unified PDO wrapper with CRUD helpers and transactions
  - `Router.php`: URL routing with parameter extraction and middleware support
  - `Session.php`: Secure session management with flash messages
  - `View.php`: Template renderer with auto-escaping and HTMX support
  - `Middleware/`: CSRF, Auth, Admin middleware

- **Controllers** (`src/Controllers/`): Handle HTTP requests
  - `AuthController.php`: Login, register, logout (with rate limiting)
  - `ProductController.php`: Product CRUD with image upload validation
  - `ForumController.php`: Forum with HTMLPurifier sanitization
  - `MessageController.php`: User messaging
  - `ProfileController.php`: User profiles
  - `DashboardController.php`: User dashboard
  - `AdminController.php`: Admin panel (products, users)

- **Models** (`src/Models/`): Database query encapsulation
  - `User.php`, `Product.php`, `Message.php`, `Forum.php`, `Review.php`

- **Views** (`src/Views/`): PHP templates organized by feature
  - `layouts/main.php`: Main layout with navbar and footer
  - `auth/`, `product/`, `dashboard/`, `forum/`, `messages/`, `profile/`, `admin/`, `errors/`

- **Routes** (`src/routes.php`): All route definitions

### Key Patterns
1. **Configuration**: All credentials in `.env`, loaded via `Config::get()`
2. **Security**: CSRF tokens on all forms, View::e() for XSS prevention, HTMLPurifier for rich text
3. **File Uploads**: MIME validation, extension whitelist, randomized filenames, 0755 permissions
4. **Authentication**: Session-based with middleware guards
5. **URL Routing**: `index.php` → `App.php` → `Router` → Middleware → Controller
6. **Database**: Prepared statements via PDO, CRUD helpers on Database class

### Database Schema
- **users**: User accounts with role-based access (user/admin)
- **products**: Product listings with approval workflow
- **messages**: User-to-user messaging
- **forum_categories/topics/replies**: Forum structure
- **reviews**: Product reviews and ratings
- **favorites**: User wishlists

## Development Notes

### Adding a New Page
1. Add route in `src/routes.php`
2. Create controller method in appropriate controller
3. Create view file in `src/Views/`
4. Use `View::e()` for output escaping, `View::csrfField()` in POST forms

### Database Changes
1. Create new SQL file in `migrations/` (e.g., `002_add_feature.sql`)
2. Run `php migrations/migrate.php`

### Git Commit Guidelines
- Never include "Co-Authored-By: Claude" in commit messages
- Never mention "Claude Code" or "Generated with Claude Code" in commits
- Write clean, professional commit messages focusing on the changes made
