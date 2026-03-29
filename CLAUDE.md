# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Laravel API Documentation Generator** — a self-analyzing tool that scans a Laravel project's routes, controllers, and FormRequests to generate browsable API documentation.

Tech stack: Laravel 12, Inertia.js (React 19), Mantine UI v8, Tailwind CSS v4, Vite 7, SQLite.

## How to Run

```bash
# First-time setup (install deps, generate key, migrate, build frontend)
composer setup

# Start development server (Laravel + Vite + Queue + Logs)
composer dev
# Then visit: http://localhost:8000/docs/api

# Generate/regenerate API documentation from routes
php artisan api-docs:generate

# Build frontend for production
npm run build
```

## Common Commands

- **Setup:** `composer setup` (installs deps, generates key, runs migrations, builds frontend)
- **Dev server:** `composer dev` (starts Laravel server, queue worker, Pail log viewer, and Vite concurrently)
- **Generate docs:** `php artisan api-docs:generate` (scans routes → populates DB → viewable at /docs/api)
- **Generate docs for external project:** `php artisan api-docs:generate --project={id}` (scans external Laravel project)
- **Run all tests:** `composer test` (clears config cache, then runs `php artisan test`)
- **Run a single test:** `php artisan test --filter=TestClassName`
- **Run API docs tests only:** `php artisan test --filter=ApiDocsTest`
- **Lint/format PHP:** `./vendor/bin/pint`
- **Build frontend:** `npm run build`
- **Dev frontend only:** `npm run dev`

## Architecture

### Backend
- **Standard Laravel structure:** `app/`, `routes/`, `config/`, `database/`
- **Services:** `app/Services/ApiDoc/` — RouteScanner, ControllerParser, RequestValidationParser, ResponseGenerator, DocGenerator, ExternalProjectScanner, ExternalControllerParser, ExternalRequestParser, ExternalResponseAnalyzer
- **Models:** `app/Models/ApiEndpoint*.php` — 4 models (Group, Endpoint, Parameter, Response); `ApiProject` — external projects with optional `project_path`
- **Controllers:** `app/Http/Controllers/ApiDocController.php` — index, show, generate; `ExternalProjectController.php` — CRUD + generate for external projects
- **Artisan command:** `app/Console/Commands/GenerateApiDocs.php` — `api-docs:generate`
- **Config:** `config/api-docs.php` — title, description, excluded route prefixes

### Frontend
- **Inertia.js with React 19** — entry point at `resources/js/app.jsx`
- **Mantine UI v8** — component library (AppShell, Badge, Table, Accordion, etc.)
- **Root template:** `resources/views/app.blade.php`
- **Pages:** `resources/js/Pages/ApiDocs/Index.jsx` and `Show.jsx`
- **Components:** `resources/js/Components/ApiDocs/` — Layout, GroupNav, SearchBar, EndpointCard, MethodBadge, ParameterTable, RequestBodyExample, ResponsePanel

### Middleware
- `HandleInertiaRequests` registered in `bootstrap/app.php` web middleware group
- Shares flash messages with Inertia pages

### Database
- SQLite by default; tests use in-memory SQLite (configured in `phpunit.xml`)
- 5 API docs tables: `api_projects`, `api_endpoint_groups`, `api_endpoints`, `api_endpoint_parameters`, `api_endpoint_responses`
- `api_projects` has optional `project_path` column for external Laravel project scanning

### Routes
| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/docs/api` | Documentation listing page |
| GET | `/docs/api/endpoints/{endpoint}` | Endpoint detail page |
| POST | `/docs/api/generate` | Trigger doc regeneration |
| POST | `/docs/api/projects/{project}/generate` | Generate docs from external Laravel project |

### Testing
- PHPUnit with `Unit` and `Feature` test suites under `tests/`
- `tests/Feature/ApiDocsTest.php` — 11 tests covering all services, artisan command, and controller
- Tests verify: route scanning, prefix exclusion, docblock parsing, FormRequest detection, validation rule mapping, response generation, DB persistence, HTTP responses

## Key Design Decisions

- **Truncate-and-rebuild:** DocGenerator clears all tables and rebuilds from routes on every generation (routes are the source of truth)
- **Self-analyzing:** The tool documents the very project it's installed in
- **Excluded prefixes:** `docs/api`, `_ignition`, `_debugbar`, `sanctum`, `up` — configurable in `config/api-docs.php`
- **External project scanning:** Uses `php artisan route:list --json` in the external project directory; parses controller/FormRequest files via regex (no autoloading of external code)
- **Rich response examples:** `ExternalResponseAnalyzer` reads model `$fillable`/`$casts` and migration column types to generate realistic response bodies with proper field names, types, and example values
- **Inline validation:** Captures `$request->validate([...])` rules in addition to FormRequest classes
- **Per-project truncate-and-rebuild:** `generateForProject()` only clears groups belonging to that project, leaving other projects and local docs untouched
- **NPM note:** `@vitejs/plugin-react@4` is used (not v6) for Vite 7 compatibility — install with `--legacy-peer-deps` if needed

## How to Add New Documented Routes

1. Create a controller with docblock comments on methods
2. Use `FormRequest` classes with `rules()` for automatic parameter extraction
3. Register routes in `routes/web.php` or `routes/api.php`
4. Run `php artisan api-docs:generate` or click "Regenerate Docs" in UI

## How to Add External Laravel Projects

1. Create a project at `/docs/api/projects/create`
2. Set the "Laravel Project Path" to the external project's root directory (e.g. `/var/www/html/my-laravel-app`)
3. Click "Generate Docs" on the project page to auto-scan routes, controllers, and FormRequests
4. Or use CLI: `php artisan api-docs:generate --project={id}`

## Change Log

See `implement.md` for a detailed log of all significant changes and implementation decisions.
