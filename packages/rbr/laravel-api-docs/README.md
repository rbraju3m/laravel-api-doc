# RBR Laravel API Doc

A self-analyzing tool that scans a Laravel project's routes, controllers, and FormRequests to generate browsable API documentation.

**Author:** Rashedul Bari Raju (rbraju3m@gmail.com)

## Installation

```bash
composer require rbr/laravel-api-docs
```

The package uses Laravel's auto-discovery, so the service provider will be registered automatically.

## Setup

### 1. Publish the config file

```bash
php artisan vendor:publish --tag=api-docs-config
```

### 2. Run the migrations

```bash
php artisan migrate
```

### 3. Publish frontend assets (optional)

```bash
php artisan vendor:publish --tag=api-docs-assets
php artisan vendor:publish --tag=api-docs-css
php artisan vendor:publish --tag=api-docs-views
```

## Usage

### Generate API Documentation

```bash
php artisan api-docs:generate
php artisan api-docs:generate --project={id}
```

### Browse Documentation

Visit `http://your-app.test/docs/api`

### Configuration

Edit `config/api-docs.php`:

```php
return [
    'title' => env('API_DOCS_TITLE', 'API Documentation'),
    'description' => env('API_DOCS_DESCRIPTION', 'Auto-generated API documentation'),
    'exclude_prefixes' => ['_ignition', '_debugbar', 'sanctum', 'docs/api', 'up'],
    'route_prefix' => 'docs/api',
    'middleware' => ['web'],
    'copyright' => 'RBR Laravel API Doc',
];
```

## Features

- Auto-scans Laravel routes, controllers, and FormRequests
- Parses docblock comments for endpoint descriptions
- Detects validation rules (FormRequest, inline, Validator::make)
- Generates realistic response examples from model $fillable, $casts, and migrations
- Supports external Laravel project scanning
- Manual endpoint CRUD
- Beautiful UI with React 19, Mantine UI v8, and Inertia.js
- Dashboard with stats, method distribution, and search

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Inertia.js with React

## License

MIT

---

&copy; RBR Laravel API Doc by Rashedul Bari Raju. All rights reserved.
