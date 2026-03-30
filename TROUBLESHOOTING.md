# Troubleshooting Guide — RBR Laravel API Doc

This document tracks all known issues encountered when developing and installing the package in host Laravel applications. Use this as a quick reference to diagnose and fix problems.

---

## Table of Contents

1. [Assets Not Found (JS/CSS not loaded)](#1-assets-not-found-jscss-not-loaded)
2. [View [app] not found — InvalidArgumentException](#2-view-app-not-found--invalidargumentexception)
3. [No hint path defined for [api-docs]](#3-no-hint-path-defined-for-api-docs)
4. [RouteScanner crash — tuple syntax without @](#4-routescanner-crash--tuple-syntax-without-)
5. [Inertia root view mismatch in host app](#5-inertia-root-view-mismatch-in-host-app)
6. [Blank page — Cannot read properties of undefined (reading 'startsWith')](#6-blank-page--cannot-read-properties-of-undefined-reading-startswith)
7. [Mantine CSS not loaded — page renders unstyled or invisible](#7-mantine-css-not-loaded--page-renders-unstyled-or-invisible)
8. [External project: "No parameters" for most endpoints](#8-external-project-no-parameters-for-most-endpoints)
9. [Inline validation not detected](#9-inline-validation-not-detected)

---

## 1. Assets Not Found (JS/CSS not loaded)

**Error Message:**
```
Assets Not Found
The API documentation assets (JS/CSS) could not be loaded.
(Searched for: /path/to/host/public/vendor/api-docs/build/manifest.json [NOT FOUND]
and /path/to/host/public/build/manifest.json [NOT FOUND])
isHot: [FALSE]
```

**When:** After installing the package in a host Laravel app and visiting `/docs/api`.

**Root Cause:**
The package's frontend (React/Mantine/Inertia) must be pre-built into `packages/rbr/laravel-api-docs/public/build/`. If the build doesn't exist when `composer install` copies the package to `vendor/`, the host app has no JS/CSS to serve. The `vendor:publish --tag=api-docs-assets-build` command also fails because the source directory doesn't exist in vendor.

**Fix (immediate — for current host app):**

```bash
# Step 1: Build assets in the package source directory
cd /var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs
npm install
npm run build
# This creates: packages/rbr/laravel-api-docs/public/build/

# Step 2: Copy built assets directly to the host app
mkdir -p /var/www/html/<host-app>/public/vendor/api-docs/build
cp -r /var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs/public/build/* \
      /var/www/html/<host-app>/public/vendor/api-docs/build/
```

**Fix (permanent — for future installs):**

```bash
# Step 1: Build assets in package (if not already built)
cd /var/www/html/laravel-api-docs/packages/rbr/laravel-api-docs
npm install && npm run build

# Step 2: Also copy build to vendor so vendor:publish works
cp -r public/build /var/www/html/<host-app>/vendor/rbr/laravel-api-docs/public/build

# Step 3: Now this command works in the host app
cd /var/www/html/<host-app>
php artisan vendor:publish --tag=api-docs-assets-build --force
```

**Prevention:**
- Always run `npm run build` inside the package directory **before** running `composer update` in any host app.
- Commit `packages/rbr/laravel-api-docs/public/build/` to git so it's always included.
- Make sure the root `.gitignore` entry `/public/build` does NOT exclude the package's build (it only excludes the root project's `public/build` — the leading `/` ensures this).

**Key Files:**
- `packages/rbr/laravel-api-docs/resources/views/app.blade.php` — asset detection logic
- `packages/rbr/laravel-api-docs/src/ApiDocServiceProvider.php` — `publishes()` for `api-docs-assets-build` tag
- `packages/rbr/laravel-api-docs/vite.config.js` — `outDir: 'public/build'`

---

## 2. View [app] not found — InvalidArgumentException

**Error Message:**
```
InvalidArgumentException: View [app] not found.
```

**When:** Visiting `/docs/api` in a host app that doesn't have a `resources/views/app.blade.php`.

**Root Cause:**
Inertia.js defaults to rendering into a root view called `app`. The host application may not define this view (e.g., it uses `welcome` or a different layout). The package needs to tell Inertia to use its own view (`api-docs::app`).

**Fix (applied in commit `0788144`):**
The package's `BaseController` sets `Inertia::setRootView('api-docs::app')` so all package controllers use the package's own blade template, not the host app's.

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Http/Controllers/BaseController.php`
- `packages/rbr/laravel-api-docs/resources/views/app.blade.php`

---

## 3. No hint path defined for [api-docs]

**Error Message:**
```
InvalidArgumentException: No hint path defined for [api-docs].
```

**When:** After installing the package, the ServiceProvider is not being discovered/loaded.

**Root Cause:**
The package was not properly linked in the host app's `composer.json`. Without the ServiceProvider booting, `loadViewsFrom(..., 'api-docs')` never runs, so Laravel doesn't know the `api-docs::` view namespace.

**Fix (applied in commit `43751b7`):**

1. Add the package as a path repository in the host app's `composer.json`:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-api-docs/packages/rbr/laravel-api-docs"
        }
    ],
    "require": {
        "rbr/laravel-api-docs": "*"
    }
}
```

2. Run `composer update rbr/laravel-api-docs`.

3. Ensure `ApiDocServiceProvider` is in the package's `composer.json` under `extra.laravel.providers`:
```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Rbr\\LaravelApiDocs\\ApiDocServiceProvider"
            ]
        }
    }
}
```

**Key Files:**
- `packages/rbr/laravel-api-docs/composer.json` — auto-discovery config
- `packages/rbr/laravel-api-docs/src/ApiDocServiceProvider.php` — registers views, routes, migrations

---

## 4. RouteScanner crash — tuple syntax without @

**Error Message:**
```
ErrorException: Undefined array key 1 (in RouteScanner.php)
```

**When:** Running `php artisan api-docs:generate` when the app has routes using array/tuple syntax.

**Root Cause:**
Routes registered as `[Controller::class, 'method']` produce action strings like `App\Http\Controllers\UserController@index`, but some (closures, invokable) produce strings **without** `@`. The `explode('@', $action)` call fails silently, returning a single-element array.

**Fix (applied in commit `9859891`):**
Check for `@` before splitting. If absent, treat the entire string as the controller class and default the method to `__invoke`.

```php
if (str_contains($action, '@')) {
    [$controller, $method] = explode('@', $action);
} else {
    $controller = $action;
    $method = '__invoke';
}
```

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Services/ApiDoc/RouteScanner.php`

---

## 5. Inertia root view mismatch in host app

**Symptom:**
Package pages render inside the host app's layout instead of the package's own layout, or render with broken styles.

**When:** The host app has its own `HandleInertiaRequests` middleware that sets a different root view.

**Root Cause:**
The host app's Inertia middleware overrides the root view globally. The package needs its own middleware to set the root view specifically for package routes.

**Fix (applied in commit `c346526`):**
Created `SetInertiaRootView` middleware that sets `Inertia::setRootView('api-docs::app')` and applied it to all package routes via route group middleware.

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Http/Middleware/SetInertiaRootView.php`
- `packages/rbr/laravel-api-docs/routes/web.php` — middleware applied to route group

---

## 6. Blank page — Cannot read properties of undefined (reading 'startsWith')

**Error Message (in browser console):**
```
Uncaught TypeError: Cannot read properties of undefined (reading 'startsWith')
```

**When:** Page loads with correct data from the backend (queries run, Inertia `data-page` is populated) but the screen is completely blank/white.

**Root Cause:**
`Layout.jsx` was destructuring `url` from `usePage().props`:
```jsx
const { url, apiDocsConfig } = usePage().props;  // WRONG
```
In Inertia.js, `url` is a top-level property on `usePage()`, **not** inside `props`. It worked in the dev project because the dev setup happened to share `url` as a prop, but host apps don't do this. When `url` is `undefined`, any `.startsWith()` call on it crashes and prevents React from mounting — resulting in a blank page.

**Fix:**
```jsx
const page = usePage();
const url = page.url || '';
const apiDocsConfig = page.props?.apiDocsConfig;
```

**Key Files:**
- `packages/rbr/laravel-api-docs/resources/js/Components/ApiDocs/Layout.jsx`

**Lesson:** Always use `usePage().url` for the current URL, and `usePage().props` only for server-shared data. Never assume dev-environment behavior matches host-app behavior.

---

## 7. Mantine CSS not loaded — page renders unstyled or invisible

**Symptom:**
Page loads, React mounts, but everything is invisible or completely unstyled (no Mantine AppShell, no sidebar, no header).

**When:** Package is installed in a host app using pre-built assets (`public/vendor/api-docs/build/`).

**Root Cause:**
The Vite build splits CSS into two files:
1. `resources/css/app.css` → Tailwind/app styles (listed as its own manifest entry)
2. Mantine CSS (`@mantine/core/styles.css` imported in `app.jsx`) → extracted as a CSS dependency of the JS entry

The manifest shows:
```json
"resources/js/app.jsx": {
    "file": "assets/app-XXX.js",
    "css": ["assets/app-YYY.css"]  // Mantine CSS here
}
```

The blade template was only loading `resources/css/app.css` from the manifest but **not** the CSS dependencies listed under the JS entry's `css` array. Without Mantine styles, components render with zero dimensions.

**Fix:**
Added a loop in `app.blade.php` to load CSS dependencies from the JS manifest entry:
```blade
@if(isset($manifest['resources/js/app.jsx']['css']))
    @foreach($manifest['resources/js/app.jsx']['css'] as $cssFile)
        <link rel="stylesheet" href="{{ asset('vendor/api-docs/build/' . $cssFile) }}">
    @endforeach
@endif
```

**Key Files:**
- `packages/rbr/laravel-api-docs/resources/views/app.blade.php`

---

## 8. External project: "No parameters" for most endpoints

**Symptom:**
After generating docs for an external project, most POST/PUT/PATCH endpoints show "No parameters" even though their FormRequests clearly have rules.

**When:** The external project's FormRequests use `$rules = [...]; return $rules;` pattern instead of `return [...]` directly.

**Root Cause:**
The `ExternalRequestParser` only matched `return [...]` with regex. Most real-world FormRequests assign rules to a variable first:
```php
$rules = ['name' => 'required', 'email' => 'required|email'];
return $rules;
```

**Fix:**
Rewrote `ExternalRequestParser::extractRules()` to use balanced bracket parsing and scan for ALL array literals in rules context — after `=`, `return`, `array_merge(`, etc.

**Patterns now supported:**
| Pattern | Example |
|---------|---------|
| `return [...]` | Direct return |
| `$rules = [...]; return $rules;` | Variable assignment |
| `$rules = array_merge($rules, [...])` | Array merge |
| Ternary rules | `'field' => $cond ? 'required' : 'nullable'` |

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Services/ApiDoc/ExternalRequestParser.php`

---

## 9. Inline validation not detected

**Symptom:**
Endpoints using `$this->validate($request, [...])` or `Validator::make(...)` show "No parameters".

**When:** Controller doesn't use a FormRequest class but validates inline.

**Root Cause:**
The parser only matched `$request->validate([...])`. Other common patterns were missed.

**Fix:**
Added support for multiple validation patterns in `ExternalControllerParser`:

```php
// All these patterns are now detected:
$request->validate([...]);
$this->validate($request, [...]);
Validator::make($request->all(), [...]);
$validator = Validator::make($data, [...]);
```

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Services/ApiDoc/ExternalControllerParser.php`

---

## Quick Checklist — Installing Package in a New Host App

1. Add path repository + require in host `composer.json`
2. Run `composer update rbr/laravel-api-docs`
3. Run `php artisan migrate` (creates api_docs tables)
4. Publish built assets: `php artisan vendor:publish --tag=api-docs-assets-build --force`
   - If this fails with "Can't locate path", manually copy:
     ```bash
     cp -r vendor/rbr/laravel-api-docs/public/build public/vendor/api-docs/build
     ```
   - If `vendor/rbr/laravel-api-docs/public/build` doesn't exist, build from source:
     ```bash
     cd <package-source>/packages/rbr/laravel-api-docs
     npm install && npm run build
     cp -r public/build /var/www/html/<host-app>/public/vendor/api-docs/build
     ```
5. Visit `/docs/api` — should load correctly
6. Run `php artisan api-docs:generate` to generate documentation
