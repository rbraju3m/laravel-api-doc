# Installation Guide — RBR Laravel API Doc

Complete step-by-step guide to install the package in any Laravel project.

---

## Requirements

- PHP >= 8.2
- Laravel 11 or 12
- `inertiajs/inertia-laravel` >= 2.0

---

## Step 1: Add the Package

### Option A: Local path (development)

Add the repository and require the package in your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs"
        }
    ],
    "require": {
        "rbr/laravel-api-docs": "*"
    }
}
```

Then install:

```bash
composer update rbr/laravel-api-docs
```

### Option B: From Packagist (when published)

```bash
composer require rbr/laravel-api-docs
```

---

## Step 2: Install Inertia (if not already installed)

The package requires `inertiajs/inertia-laravel`. If your project doesn't have it:

```bash
composer require inertiajs/inertia-laravel
```

> **Note:** You do NOT need to set up Inertia's frontend in your host app. The package ships its own React frontend as pre-built assets.

---

## Step 3: Run Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `api_projects`
- `api_endpoint_groups`
- `api_endpoints`
- `api_endpoint_parameters`
- `api_endpoint_responses`

---

## Step 4: Publish the Pre-Built Frontend Assets

```bash
php artisan vendor:publish --tag=api-docs-assets-build --force
```

This copies the JS/CSS build to `public/vendor/api-docs/build/`.

### If the command fails with "Can't locate path"

This means the build files weren't included when composer installed the package. Fix manually:

```bash
# Copy from the package source
cp -r /var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs/public/build \
      public/vendor/api-docs/build
```

If the package source also doesn't have `public/build/`, build from source first:

```bash
cd /var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs
npm install
npm run build
# Then copy as above
```

---

## Step 5: Generate Documentation

```bash
php artisan api-docs:generate
```

This scans your project's routes, controllers, and FormRequests to populate the documentation database.

---

## Step 6: Visit the Documentation

Open your browser and go to:

```
http://your-app.local/docs/api
```

You should see the API Documentation page with all your endpoints listed.

---

## Optional: Publish Config

```bash
php artisan vendor:publish --tag=api-docs-config
```

This publishes `config/api-docs.php` where you can customize:

```php
return [
    'title' => 'My API Documentation',        // Page title
    'description' => 'My API description',     // Page description
    'route_prefix' => 'docs/api',              // URL prefix for docs
    'middleware' => ['web'],                    // Route middleware
    'exclude_prefixes' => [                    // Routes to exclude
        '_ignition', '_debugbar', 'sanctum',
        'docs/api', 'docs/api/projects', 'up',
    ],
];
```

---

## Optional: Publish Other Assets

```bash
# Publish migrations (if you need to modify them)
php artisan vendor:publish --tag=api-docs-migrations

# Publish views (if you need to customize the blade template)
php artisan vendor:publish --tag=api-docs-views

# Publish images
php artisan vendor:publish --tag=api-docs-images
```

---

## Quick Reference — All Commands

```bash
# Install
composer require rbr/laravel-api-docs           # or add path repo + composer update
php artisan migrate                               # create tables
php artisan vendor:publish --tag=api-docs-assets-build --force  # publish JS/CSS

# Generate docs
php artisan api-docs:generate                     # scan local project
php artisan api-docs:generate --project=1         # scan external project by ID

# Optional publishing
php artisan vendor:publish --tag=api-docs-config  # publish config
```

---

## Routes Provided by the Package

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/docs/api` | Documentation listing page |
| GET | `/docs/api/endpoints/{endpoint}` | Endpoint detail page |
| POST | `/docs/api/generate` | Trigger doc regeneration (from UI) |
| GET | `/docs/api/projects` | External projects listing |
| POST | `/docs/api/projects/{project}/generate` | Generate docs for external project |

---

## Adding External Laravel Projects

1. Visit `/docs/api/projects/create`
2. Enter project name and Laravel project path (e.g., `/var/www/html/my-other-app`)
3. Click "Create Project"
4. On the project page, click "Generate Docs"

Or via CLI:

```bash
php artisan api-docs:generate --project={id}
```

---

## Updating the Package

After updating the package source code:

```bash
# 1. Update composer
composer update rbr/laravel-api-docs

# 2. Run any new migrations
php artisan migrate

# 3. Re-publish the built assets (important!)
php artisan vendor:publish --tag=api-docs-assets-build --force

# 4. Clear view cache
php artisan view:clear

# 5. Regenerate docs
php artisan api-docs:generate
```

---

## Troubleshooting

If something goes wrong, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for all known issues and fixes.

### Common Issues at a Glance

| Symptom | Likely Cause | Quick Fix |
|---------|-------------|-----------|
| "Assets Not Found" page | Build files not published | `php artisan vendor:publish --tag=api-docs-assets-build --force` |
| Blank white page | JS error — `url` from `usePage()` | Update package to latest version |
| "View [app] not found" | Inertia root view not set | Update package to latest version |
| "No hint path for [api-docs]" | ServiceProvider not loaded | Check `composer.json` has the package required |
| Page unstyled / invisible | Mantine CSS not loaded | Update package to latest version, re-publish assets |
| No endpoints shown | Docs not generated yet | Run `php artisan api-docs:generate` |
