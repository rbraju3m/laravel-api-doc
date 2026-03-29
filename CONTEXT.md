# Project Context - Quick Reference

## What Is This?

A **Laravel API Documentation Generator** that self-analyzes the project it's installed in. It scans routes, controllers, and FormRequests to auto-generate browsable API documentation at `/docs/api`.

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | 12 |
| Frontend Bridge | Inertia.js | 2.x |
| Frontend | React | 19 |
| UI Library | Mantine | 8.x |
| CSS | Tailwind CSS | 4.x |
| Bundler | Vite | 7.x |
| React Plugin | @vitejs/plugin-react | 4.x (NOT v6 — Vite 7 compat) |
| Database | SQLite | default |
| PHP | | 8.2+ |
| Testing | PHPUnit | 11.x |

## Key URLs (when running)

- **Docs UI:** http://localhost:8000/docs/api
- **Endpoint Detail:** http://localhost:8000/docs/api/endpoints/{id}
- **Welcome:** http://localhost:8000

## Quick Commands

```bash
composer setup          # Full first-time setup
composer dev            # Start dev server (all services)
composer test           # Run all tests
php artisan api-docs:generate  # Regenerate docs from routes
npm run build           # Production frontend build
./vendor/bin/pint       # PHP code formatting
```

## Key Files to Know

| File | What It Does |
|------|-------------|
| `bootstrap/app.php` | Registers HandleInertiaRequests middleware |
| `config/api-docs.php` | Title, description, excluded prefixes |
| `routes/web.php` | All routes: welcome, docs UI, sample API |
| `app/Services/ApiDoc/DocGenerator.php` | Main orchestrator (scan → parse → persist) |
| `app/Services/ApiDoc/RouteScanner.php` | Reads Route::getRoutes() |
| `app/Services/ApiDoc/ControllerParser.php` | Reflection-based controller analysis |
| `app/Services/ApiDoc/RequestValidationParser.php` | FormRequest rules → param types |
| `app/Services/ApiDoc/ResponseGenerator.php` | HTTP method → example responses |
| `app/Http/Controllers/ApiDocController.php` | Web controller (index, show, generate) |
| `app/Console/Commands/GenerateApiDocs.php` | Artisan command |
| `resources/js/app.jsx` | Inertia + Mantine bootstrap |
| `resources/js/Pages/ApiDocs/Index.jsx` | Main listing page |
| `resources/js/Pages/ApiDocs/Show.jsx` | Endpoint detail page |
| `resources/views/app.blade.php` | Inertia root template |
| `vite.config.js` | Laravel + Tailwind + React plugins |
| `tests/Feature/ApiDocsTest.php` | 11 tests for all services + controller |

## Database Tables

```
api_endpoint_groups     → Groups routes by URL prefix
  └── api_endpoints     → Individual routes (method, URI, controller, middleware)
        ├── api_endpoint_parameters  → Request params (from FormRequest rules)
        └── api_endpoint_responses   → Example responses (by HTTP method)
```

## How Doc Generation Works

```
Routes (source of truth)
  → RouteScanner reads all registered routes
  → ControllerParser reflects on controller methods (docblocks, FormRequest type-hints)
  → RequestValidationParser extracts validation rules → parameter types + examples
  → ResponseGenerator creates status code examples (200/201/204 + 422/401)
  → DocGenerator orchestrates all above in a DB transaction (truncate + rebuild)
```

## Common Gotchas

1. **`@vitejs/plugin-react` version**: Must use v4, not v6. v6 requires Vite 8.
2. **`npm install` errors**: Use `--legacy-peer-deps` flag.
3. **Blank docs page**: Run `php artisan api-docs:generate` first.
4. **New routes not showing**: Re-run `php artisan api-docs:generate` or click "Regenerate Docs" in UI.
5. **Frontend entry**: `app.jsx` not `app.js` — vite.config.js input must match.
6. **Tailwind JSX**: `@source '../**/*.jsx'` in app.css ensures Tailwind scans JSX files.

## Test Results (expected)

```
13 tests, 34 assertions — all passing
- 11 ApiDocsTest (services + command + controller)
- 1 ExampleTest (welcome page)
- 1 Unit ExampleTest (true is true)
```

## For Detailed Implementation

See `IMPLEMENTATION.md` for:
- Step-by-step build instructions (all 21 steps)
- Complete file structure with descriptions
- Database schema details
- Full prompt to regenerate from scratch
- Architecture diagrams and data flow
