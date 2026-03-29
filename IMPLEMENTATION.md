# API Documentation Generator - Full Implementation Guide

## Overview

A self-analyzing Laravel API Documentation Generator that automatically scans the project's routes, controllers, and FormRequests to produce browsable API documentation. Built with Laravel 12 + Inertia.js (React 19) + Mantine UI.

---

## How to Run the Project

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+ & npm
- SQLite

### Quick Start (Fresh Clone)

```bash
# 1. Clone the repository
git clone <repo-url> laravel-api-docs
cd laravel-api-docs

# 2. Run full setup (installs PHP & JS deps, generates app key, runs migrations, builds frontend)
composer setup

# 3. Generate API documentation from routes
php artisan api-docs:generate

# 4. Start development server
composer dev

# 5. Open browser
#    Documentation UI: http://localhost:8000/docs/api
#    Welcome page:     http://localhost:8000
```

### Manual Step-by-Step Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database (if not exists)
touch database/database.sqlite

# Run migrations (creates users, cache, jobs, and 4 api_docs tables)
php artisan migrate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build

# Generate API documentation
php artisan api-docs:generate

# Start dev server (Laravel + Vite + Queue + Logs — all concurrent)
composer dev
```

### Verify Everything Works

```bash
# Run all tests (should show 13 passed, 34 assertions)
composer test

# Check docs were generated (should show endpoints in 3 groups)
php artisan api-docs:generate

# Build frontend (should compile without errors)
npm run build
```

### Troubleshooting

| Issue | Solution |
|-------|----------|
| `npm install` peer dependency errors | Run `npm install --legacy-peer-deps` |
| `@vitejs/plugin-react` version mismatch | Must use v4 (not v6) with Vite 7: `npm install @vitejs/plugin-react@4` |
| SQLite database not found | Run `touch database/database.sqlite` then `php artisan migrate` |
| Blank page at `/docs/api` | Run `php artisan api-docs:generate` first, then `npm run build` |
| Migration already exists error | Run `php artisan migrate:fresh` (warning: drops all tables) |

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Frontend (React 19)                │
│  Pages/ApiDocs/Index.jsx  ←→  Pages/ApiDocs/Show.jsx│
│  Components: Layout, GroupNav, SearchBar,            │
│  EndpointCard, MethodBadge, ParameterTable,          │
│  ResponsePanel                                       │
├─────────────────────────────────────────────────────┤
│              Inertia.js (Bridge Layer)               │
│  HandleInertiaRequests middleware → shares flash data│
├─────────────────────────────────────────────────────┤
│                 Backend (Laravel 12)                  │
│  ApiDocController → DocGenerator (orchestrator)      │
│    ├── RouteScanner (Route::getRoutes)               │
│    ├── ControllerParser (Reflection API)             │
│    ├── RequestValidationParser (FormRequest rules)   │
│    └── ResponseGenerator (status codes/examples)     │
├─────────────────────────────────────────────────────┤
│              Database (SQLite)                        │
│  api_endpoint_groups → api_endpoints                 │
│    → api_endpoint_parameters                         │
│    → api_endpoint_responses                          │
└─────────────────────────────────────────────────────┘
```

### Data Flow

```
php artisan api-docs:generate
  → DocGenerator::generate()
    → RouteScanner::scan()           # Reads all Laravel routes
    → ControllerParser::parse()      # Reflects on controller methods
    → RequestValidationParser::parse() # Extracts FormRequest rules
    → ResponseGenerator::generate()  # Creates example responses
    → DB transaction: truncate all → insert fresh data

GET /docs/api
  → ApiDocController::index()
    → Query ApiEndpointGroup with endpoints
    → Inertia::render('ApiDocs/Index', { groups })
    → React renders Index.jsx with Mantine UI

GET /docs/api/endpoints/{id}
  → ApiDocController::show()
    → Load endpoint with group, parameters, responses
    → Inertia::render('ApiDocs/Show', { endpoint })
    → React renders Show.jsx with full detail

POST /docs/api/generate
  → ApiDocController::generate()
    → DocGenerator::generate()
    → Redirect to index with flash message
```

---

## Complete File Structure

```
app/
├── Console/Commands/
│   └── GenerateApiDocs.php          # Artisan: api-docs:generate
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php           # Base controller
│   │   ├── ApiDocController.php     # index(), show(), generate()
│   │   └── SampleApiController.php  # Demo: index, store, show, destroy
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php # Shares flash data
│   └── Requests/
│       └── SampleStoreRequest.php   # Demo: name, email, age, is_active
├── Models/
│   ├── User.php                     # Default Laravel user
│   ├── ApiEndpointGroup.php         # hasMany endpoints
│   ├── ApiEndpoint.php              # belongsTo group, hasMany params/responses
│   ├── ApiEndpointParameter.php     # belongsTo endpoint
│   └── ApiEndpointResponse.php      # belongsTo endpoint
└── Services/ApiDoc/
    ├── RouteScanner.php             # Route::getRoutes() + prefix filter
    ├── ControllerParser.php         # ReflectionMethod + docblock + FormRequest
    ├── RequestValidationParser.php  # rules() → types + required + examples
    ├── ResponseGenerator.php        # HTTP method → status codes
    └── DocGenerator.php             # Orchestrator: scan → parse → persist

bootstrap/
└── app.php                          # HandleInertiaRequests in web middleware

config/
└── api-docs.php                     # title, description, exclude_prefixes

database/migrations/
├── 0001_01_01_000000_create_users_table.php
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
└── 2026_03_16_000000_create_api_docs_tables.php  # 4 API doc tables

resources/
├── css/
│   └── app.css                      # Tailwind v4 + JSX source directive
├── js/
│   ├── app.jsx                      # Inertia + Mantine bootstrap
│   ├── bootstrap.js                 # Axios config
│   ├── Components/ApiDocs/
│   │   ├── Layout.jsx               # Mantine AppShell (header + sidebar + main)
│   │   ├── GroupNav.jsx             # Sidebar: NavLink per group
│   │   ├── SearchBar.jsx            # TextInput filter
│   │   ├── EndpointCard.jsx         # Card: method badge + URI + badges
│   │   ├── MethodBadge.jsx          # Colored badge (GET=blue, POST=green, etc.)
│   │   ├── ParameterTable.jsx       # Striped table: name, location, type, required, rules, example
│   │   └── ResponsePanel.jsx        # Accordion: status badge + CodeHighlight JSON
│   └── Pages/ApiDocs/
│       ├── Index.jsx                # Listing: sidebar groups, search, endpoint cards, regenerate btn
│       └── Show.jsx                 # Detail: method+URI, controller, middleware, params, responses
└── views/
    ├── app.blade.php                # Inertia root template (@viteReactRefresh + @inertia)
    └── welcome.blade.php            # Default Laravel welcome page

routes/
├── web.php                          # / (welcome), /docs/api/*, /api/samples/*
└── console.php                      # Artisan inspire command

tests/
├── TestCase.php
├── Feature/
│   ├── ExampleTest.php
│   └── ApiDocsTest.php              # 11 tests: services + command + controller
└── Unit/
    └── ExampleTest.php

vite.config.js                       # laravel() + tailwindcss() + react()
```

---

## Step-by-Step Implementation Guide

Use this guide to reproduce the entire implementation from a fresh Laravel 12 + Inertia.js project.

### Phase 1: Foundation (Backend Setup)

#### Step 1: Register Inertia middleware

**File:** `bootstrap/app.php`

Add `HandleInertiaRequests` to the web middleware group:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
})
```

Update `HandleInertiaRequests` to share flash data:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'flash' => [
            'message' => fn () => $request->session()->get('message'),
        ],
    ];
}
```

#### Step 2: Create database migration

**File:** `database/migrations/2026_03_16_000000_create_api_docs_tables.php`

4 tables:
- `api_endpoint_groups`: id, name, prefix, description, sort_order, timestamps
- `api_endpoints`: id, api_endpoint_group_id (FK), http_method, uri, name, controller_class, controller_method, description, middleware (json), is_authenticated (bool), is_closure (bool), timestamps
- `api_endpoint_parameters`: id, api_endpoint_id (FK), name, location (enum: query/body/uri), type, required (bool), description, rules, example, timestamps
- `api_endpoint_responses`: id, api_endpoint_id (FK), status_code, description, content_type, example_body (json), timestamps

All FKs use `cascadeOnDelete()`.

#### Step 3: Create Eloquent models

4 models with relationships:
- `ApiEndpointGroup` — `hasMany(ApiEndpoint::class)`
- `ApiEndpoint` — `belongsTo(ApiEndpointGroup)`, `hasMany(Parameter)`, `hasMany(Response)`, casts: middleware=array, booleans
- `ApiEndpointParameter` — `belongsTo(ApiEndpoint)`, cast: required=boolean
- `ApiEndpointResponse` — `belongsTo(ApiEndpoint)`, cast: example_body=array

#### Step 4: Create config

**File:** `config/api-docs.php`

```php
return [
    'title' => env('API_DOCS_TITLE', 'API Documentation'),
    'description' => env('API_DOCS_DESCRIPTION', 'Auto-generated API documentation'),
    'exclude_prefixes' => ['_ignition', '_debugbar', 'sanctum', 'docs/api', 'up'],
];
```

### Phase 2: Service Layer

#### Step 5: RouteScanner

**File:** `app/Services/ApiDoc/RouteScanner.php`

- Iterates `Route::getRoutes()`
- Skips routes whose URI starts with any excluded prefix
- Skips HEAD method
- Returns array of: http_method, uri, name, controller_class, controller_method, middleware, is_closure, prefix
- Extracts prefix as first URI segment

#### Step 6: ControllerParser

**File:** `app/Services/ApiDoc/ControllerParser.php`

- Uses `ReflectionMethod` on controller class + method
- Extracts docblock first non-`@` line as description
- Detects `FormRequest` subclass type-hints → returns form_request_class
- Detects primitive type-hints and model bindings → returns uri_parameters
- Returns: description, form_request_class, return_type, uri_parameters

#### Step 7: RequestValidationParser

**File:** `app/Services/ApiDoc/RequestValidationParser.php`

- Instantiates FormRequest, calls `rules()`
- Normalizes rules (string pipe-delimited or array)
- Maps rules to types: integer, numeric→number, boolean, array, file, image→file, email→string(email), date, url, json, string
- Detects required/optional
- Generates examples based on rules and field name patterns

#### Step 8: ResponseGenerator

**File:** `app/Services/ApiDoc/ResponseGenerator.php`

- POST → 201 Created, DELETE → 204 No Content, others → 200 OK
- If hasValidation → add 422 with example errors
- If isAuthenticated → add 401

#### Step 9: DocGenerator (Orchestrator)

**File:** `app/Services/ApiDoc/DocGenerator.php`

- Constructor-injected: RouteScanner, ControllerParser, RequestValidationParser, ResponseGenerator
- `generate()`: DB transaction, truncate all 4 tables, scan routes, group by prefix
- For each group: create ApiEndpointGroup
- For each route: parse controller → create ApiEndpoint → create parameters → create responses
- Returns stats: `['groups' => N, 'endpoints' => N]`

#### Step 10: Artisan Command

**File:** `app/Console/Commands/GenerateApiDocs.php`

- Signature: `api-docs:generate`
- Calls `DocGenerator::generate()` via DI
- Outputs: "Done! Generated X endpoints in Y groups."

### Phase 3: Web Controller & Routes

#### Step 11: ApiDocController

**File:** `app/Http/Controllers/ApiDocController.php`

- `index()`: Load groups with endpoints, render `ApiDocs/Index` via Inertia
- `show(ApiEndpoint $endpoint)`: Load endpoint with relations, render `ApiDocs/Show`
- `generate(DocGenerator $generator)`: Call generate, redirect with flash message

#### Step 12: Routes

**File:** `routes/web.php`

```php
Route::prefix('docs/api')->name('api-docs.')->group(function () {
    Route::get('/', [ApiDocController::class, 'index'])->name('index');
    Route::get('/endpoints/{endpoint}', [ApiDocController::class, 'show'])->name('show');
    Route::post('/generate', [ApiDocController::class, 'generate'])->name('generate');
});
```

### Phase 4: Frontend

#### Step 13: Install NPM dependencies

```bash
npm install --legacy-peer-deps @mantine/core @mantine/hooks @mantine/code-highlight @vitejs/plugin-react@4
```

**Important:** Use `@vitejs/plugin-react@4` (not v6) for Vite 7 compatibility.

#### Step 14: Inertia root template

**File:** `resources/views/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('api-docs.title', 'API Documentation') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

#### Step 15: Update vite.config.js

- Add `import react from '@vitejs/plugin-react'`
- Change input from `app.js` to `app.jsx`
- Add `react()` to plugins array

#### Step 16: Create app.jsx

**File:** `resources/js/app.jsx`

- Import Mantine CSS: `@mantine/core/styles.css`, `@mantine/code-highlight/styles.css`
- `createInertiaApp` with `import.meta.glob('./Pages/**/*.jsx', { eager: true })`
- Wrap in `MantineProvider`

#### Step 17: Update CSS

**File:** `resources/css/app.css`

Add `@source '../**/*.jsx';` so Tailwind scans JSX files.

#### Step 18: Create React components

7 components in `resources/js/Components/ApiDocs/`:

| Component | Purpose |
|-----------|---------|
| `Layout.jsx` | Mantine `AppShell` with header (title), sidebar (nav), main content area. Responsive burger menu. |
| `GroupNav.jsx` | `NavLink` list for groups with "All Endpoints" option. Shows endpoint count per group. |
| `SearchBar.jsx` | `TextInput` for filtering endpoints by URI, method, name, or description. |
| `EndpointCard.jsx` | Clickable `Card` with method badge, URI code, auth/name badges. Links to show page. |
| `MethodBadge.jsx` | Colored `Badge`: GET=blue, POST=green, PUT=orange, PATCH=yellow, DELETE=red. Monospace font. |
| `ParameterTable.jsx` | Striped `Table` with columns: name (code), location (badge), type, required (badge), rules, example. |
| `ResponsePanel.jsx` | `Accordion` per status code with colored badge and `CodeHighlight` for JSON body. |

#### Step 19: Create pages

**`Pages/ApiDocs/Index.jsx`:**
- State: search text, active group ID, generating flag
- Sidebar: `GroupNav` component
- Main: title + description, "Regenerate Docs" button, flash alert, search bar, filtered endpoint cards
- Filtering: by group (sidebar) and search text (URI, method, name, description)

**`Pages/ApiDocs/Show.jsx`:**
- Sidebar: "Back to all endpoints" button
- Main: method badge + URI title, route name/auth/closure/group badges, description, controller code block, middleware badges, ParameterTable, ResponsePanel

### Phase 5: Testing

#### Step 20: Sample routes for testing

- `SampleApiController` with 4 methods: index, store (with FormRequest), show, destroy
- `SampleStoreRequest` with rules: name (required|string), email (required|email), age (nullable|integer), is_active (boolean)
- Routes at `/api/samples` prefix

#### Step 21: PHPUnit tests

**File:** `tests/Feature/ApiDocsTest.php` — 11 tests:

1. Route scanner returns routes (finds /api/samples)
2. Route scanner excludes docs routes (no /docs/api)
3. Controller parser extracts docblock ("List all samples.")
4. Controller parser detects FormRequest class
5. Validation parser extracts rules (types, required, email detection)
6. Response generator creates correct status codes (201, 422, 401)
7. Doc generator creates database records
8. Artisan command runs successfully (exit code 0)
9. Index page loads (HTTP 200)
10. Show page loads (HTTP 200, after generating docs)
11. Generate endpoint works (POST creates records, redirects)

---

## Database Schema

### `api_endpoint_groups`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Display name (ucfirst of prefix) |
| prefix | string | First URI segment |
| description | text | Optional group description |
| sort_order | integer | Display ordering (default 0) |
| timestamps | | created_at, updated_at |

### `api_endpoints`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| api_endpoint_group_id | FK → groups | Parent group |
| http_method | string | GET, POST, PUT, PATCH, DELETE |
| uri | string | Full route URI |
| name | string | Laravel route name |
| controller_class | string | FQCN of controller |
| controller_method | string | Method name |
| description | text | From controller docblock |
| middleware | json | Array of middleware names |
| is_authenticated | boolean | Has auth:* middleware |
| is_closure | boolean | Closure vs controller route |
| timestamps | | created_at, updated_at |

### `api_endpoint_parameters`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| api_endpoint_id | FK → endpoints | Parent endpoint |
| name | string | Parameter name |
| location | enum | query, body, uri |
| type | string | string, integer, boolean, array, file, etc. |
| required | boolean | From validation rules |
| description | text | Parameter description |
| rules | text | Raw validation rule string |
| example | text | Auto-generated example value |
| timestamps | | created_at, updated_at |

### `api_endpoint_responses`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| api_endpoint_id | FK → endpoints | Parent endpoint |
| status_code | integer | HTTP status code |
| description | string | Human-readable status |
| content_type | string | Default: application/json |
| example_body | json | Example response body |
| timestamps | | created_at, updated_at |

---

## Configuration Reference

**File:** `config/api-docs.php`

| Key | Default | Description |
|-----|---------|-------------|
| `title` | `API Documentation` | Displayed in header and page title |
| `description` | `Auto-generated API documentation` | Shown below title on index page |
| `exclude_prefixes` | `['_ignition', '_debugbar', 'sanctum', 'docs/api', 'up']` | Route URI prefixes to skip |

Override via `.env`:
```
API_DOCS_TITLE="My Project API"
API_DOCS_DESCRIPTION="Internal API reference for My Project"
```

---

## How to Extend

### Add new documented routes
1. Create controller with docblock comments
2. Create FormRequest with `rules()` for parameter extraction
3. Register routes in `routes/web.php` or `routes/api.php`
4. Run `php artisan api-docs:generate`

### Add new parameter detection
Edit `RequestValidationParser::detectType()` to map new validation rules to types.

### Add new response codes
Edit `ResponseGenerator::generate()` to add conditions for additional status codes.

### Customize excluded routes
Edit `config/api-docs.php` → `exclude_prefixes` array.

---

## Full Prompt for Regenerating This Implementation

Use this prompt with Claude Code to recreate the entire implementation from a fresh Laravel 12 + Inertia.js project:

```
Implement a Laravel API Documentation Generator that automatically scans the project's
routes, controllers, and FormRequests to generate browsable API documentation.

Tech stack: Laravel 12, Inertia.js, React 19, Mantine UI v8, Tailwind CSS v4, Vite 7, SQLite.

## Phase 1: Foundation

1. Register HandleInertiaRequests middleware in bootstrap/app.php (web middleware group).
   Update it to share flash messages: `'flash' => ['message' => fn () => $request->session()->get('message')]`

2. Create migration `2026_03_16_000000_create_api_docs_tables.php` with 4 tables:
   - api_endpoint_groups: id, name, prefix, description, sort_order, timestamps
   - api_endpoints: id, api_endpoint_group_id (FK cascade), http_method, uri, name,
     controller_class, controller_method, description, middleware (json),
     is_authenticated (bool), is_closure (bool), timestamps
   - api_endpoint_parameters: id, api_endpoint_id (FK cascade), name,
     location (enum: query/body/uri), type, required (bool), description, rules, example, timestamps
   - api_endpoint_responses: id, api_endpoint_id (FK cascade), status_code,
     description, content_type, example_body (json), timestamps

3. Create 4 Eloquent models with relationships and casts:
   - ApiEndpointGroup (hasMany endpoints)
   - ApiEndpoint (belongsTo group, hasMany parameters/responses, cast middleware=array, booleans)
   - ApiEndpointParameter (belongsTo endpoint, cast required=boolean)
   - ApiEndpointResponse (belongsTo endpoint, cast example_body=array)

4. Create config/api-docs.php:
   title (env API_DOCS_TITLE), description (env API_DOCS_DESCRIPTION),
   exclude_prefixes: [_ignition, _debugbar, sanctum, docs/api, up]

## Phase 2: Service Layer (app/Services/ApiDoc/)

5. RouteScanner: iterate Route::getRoutes(), skip excluded prefixes and HEAD method,
   extract http_method/uri/name/controller_class/controller_method/middleware/is_closure/prefix

6. ControllerParser: ReflectionMethod to get docblock description (first non-@ lines),
   detect FormRequest type-hints, detect URI params (primitive types + model bindings)

7. RequestValidationParser: instantiate FormRequest, call rules(), normalize rules,
   map to types (integer, number, boolean, array, file, email, date, url, json, string),
   detect required, generate example values by field name and type

8. ResponseGenerator: POST→201, DELETE→204, default→200.
   Add 422 if hasValidation, 401 if isAuthenticated

9. DocGenerator (orchestrator): constructor-inject all 4 services, generate() method:
   DB transaction, truncate all 4 tables, scan routes, group by prefix,
   create groups → endpoints → parameters → responses. Return stats array.

10. Artisan command `api-docs:generate`: calls DocGenerator, outputs summary

## Phase 3: Controller & Routes

11. ApiDocController:
    - index(): load groups with endpoints, Inertia::render('ApiDocs/Index')
    - show(ApiEndpoint): load with relations, Inertia::render('ApiDocs/Show')
    - generate(): call DocGenerator, redirect with flash

12. Routes in web.php:
    GET /docs/api → index, GET /docs/api/endpoints/{endpoint} → show,
    POST /docs/api/generate → generate

## Phase 4: Frontend

13. Install: npm install --legacy-peer-deps @mantine/core @mantine/hooks
    @mantine/code-highlight @vitejs/plugin-react@4
    (MUST use @vitejs/plugin-react@4 for Vite 7 compatibility)

14. Create resources/views/app.blade.php: @viteReactRefresh, @vite app.css+app.jsx, @inertiaHead, @inertia

15. Update vite.config.js: add react() plugin, change input to app.jsx

16. Create resources/js/app.jsx: import Mantine CSS, createInertiaApp with
    import.meta.glob Pages, wrap in MantineProvider

17. Add @source '../**/*.jsx' to resources/css/app.css

18. Components in resources/js/Components/ApiDocs/:
    - Layout.jsx: Mantine AppShell (header 60px, navbar 280px, responsive burger)
    - GroupNav.jsx: NavLink per group + "All Endpoints" option
    - SearchBar.jsx: TextInput placeholder "Search endpoints..."
    - EndpointCard.jsx: Card with MethodBadge + Code URI + auth/name badges, Link to show
    - MethodBadge.jsx: Badge with color map (GET=blue, POST=green, PUT=orange, DELETE=red)
    - ParameterTable.jsx: Table with name/location/type/required/rules/example columns
    - ResponsePanel.jsx: Accordion with status badge + CodeHighlight JSON

19. Pages:
    - Pages/ApiDocs/Index.jsx: search state, group filter, regenerate button,
      flash alert, filtered endpoint cards
    - Pages/ApiDocs/Show.jsx: full detail with back button sidebar,
      method+URI header, badges, controller info, middleware, params table, responses

## Phase 5: Testing

20. Create SampleApiController (index/store/show/destroy with docblocks) and
    SampleStoreRequest (name required|string, email required|email, age nullable|integer,
    is_active boolean). Register routes at /api/samples.

21. tests/Feature/ApiDocsTest.php: 11 tests covering route scanning, prefix exclusion,
    docblock extraction, FormRequest detection, validation parsing, response generation,
    DB persistence, artisan command, index page, show page, generate endpoint.

## Verification
1. php artisan migrate
2. php artisan api-docs:generate → "Generated 7 endpoints in 3 groups"
3. npm run build → compiles without errors
4. composer test → 13 tests pass (34 assertions)
5. composer dev → visit http://localhost:8000/docs/api
```
