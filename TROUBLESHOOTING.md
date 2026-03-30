# Troubleshooting Guide — RBR Laravel API Doc

All known issues encountered during development and host-app installation, organized by date.

---

## Table of Contents

### 2026-03-17 (Feature Development)
1. [Inline validation not detected](#1-inline-validation-not-detected)
2. [External project: "No parameters" for most endpoints](#2-external-project-no-parameters-for-most-endpoints)

### 2026-03-29 (Package Extraction & Host App Installation)
3. [RouteScanner crash — tuple syntax without @](#3-routescanner-crash--tuple-syntax-without-)
4. [View [app] not found — InvalidArgumentException](#4-view-app-not-found--invalidargumentexception)
5. [No hint path defined for [api-docs]](#5-no-hint-path-defined-for-api-docs)
6. [Inertia root view mismatch in host app](#6-inertia-root-view-mismatch-in-host-app)

### 2026-03-30 (Host App Asset & Rendering Fixes)
7. [Assets Not Found (JS/CSS not loaded)](#7-assets-not-found-jscss-not-loaded)
8. [Mantine CSS not loaded — page renders unstyled or invisible](#8-mantine-css-not-loaded--page-renders-unstyled-or-invisible)
9. [Blank page — Cannot read properties of undefined (reading 'startsWith')](#9-blank-page--cannot-read-properties-of-undefined-reading-startswith)
10. [Auto-publish fails with permission denied](#10-auto-publish-fails-with-permission-denied)

---

# 2026-03-17 — Feature Development

Issues discovered while building the external project scanning feature.

---

## 1. Inline validation not detected

**Date:** 2026-03-17

**Symptom:**
Endpoints using `$this->validate($request, [...])` or `Validator::make(...)` show "No parameters".

**When:** Controller doesn't use a FormRequest class but validates inline.

**Root Cause:**
The parser only matched `$request->validate([...])`. Other common Laravel validation patterns were missed:
- `$this->validate($request, [...])` — ValidatesRequests trait
- `Validator::make($request->all(), [...])` — manual validator
- Custom validation messages as second array `[...], [...]` broke bracket matching

**Fix:**
Added support for multiple validation patterns in `ExternalControllerParser`:
- `findValidationRulesArray()` — tries 4 patterns
- `extractBracketBlock()` — balanced bracket parser that correctly stops at matching `]`
- `parseRulesArray()` — shared logic for parsing `'field' => 'rules'` entries

```php
// All patterns now detected:
$request->validate([...]);
$this->validate($request, [...]);
Validator::make($request->all(), [...]);
$validator = Validator::make($data, [...]);
```

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Services/ApiDoc/ExternalControllerParser.php`

---

## 2. External project: "No parameters" for most endpoints

**Date:** 2026-03-17

**Symptom:**
After generating docs for an external project, most POST/PUT/PATCH endpoints show "No parameters" even though their FormRequests clearly have rules.

**When:** The external project's FormRequests use `$rules = [...]; return $rules;` pattern instead of `return [...]` directly.

**Root Cause:**
`ExternalRequestParser` only matched `return [...]` with regex. Most real-world FormRequests assign rules to a variable first:
```php
$rules = ['name' => 'required', 'email' => 'required|email'];
return $rules;
```

**Impact:** Before fix: 95 endpoints with params. After fix: 115 endpoints with params (POST/PUT/PATCH coverage: 83%).

**Fix:**
Rewrote `ExternalRequestParser::extractRules()` to use balanced bracket parsing and scan for ALL array literals in rules context.

**Patterns now supported:**
| Pattern | Example |
|---------|---------|
| `return [...]` | Direct return |
| `$rules = [...]; return $rules;` | Variable assignment (most common) |
| `$rules = array_merge($rules, [...])` | Array merge |
| Ternary rules | `'field' => $cond ? 'required' : 'nullable'` |

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Services/ApiDoc/ExternalRequestParser.php`

---

# 2026-03-29 — Package Extraction & Host App Installation

Issues discovered when extracting the code into a standalone Laravel package and installing it in a host app (`appza-backend`).

---

## 3. RouteScanner crash — tuple syntax without @

**Date:** 2026-03-29 | **Commit:** `9859891`

**Error Message:**
```
ErrorException: Undefined array key 1 (in RouteScanner.php)
```

**When:** Running `php artisan api-docs:generate` when the app has routes using array/tuple syntax.

**Root Cause:**
Routes registered as `[Controller::class, 'method']` produce action strings like `App\Http\Controllers\UserController@index`, but some (closures, invokable controllers) produce strings **without** `@`. The `explode('@', $action)` call returns a single-element array, causing index `[1]` to be undefined.

**Fix:**
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

## 4. View [app] not found — InvalidArgumentException

**Date:** 2026-03-29 | **Commit:** `0788144`

**Error Message:**
```
InvalidArgumentException: View [app] not found.
```

**When:** Visiting `/docs/api` in a host app that doesn't have a `resources/views/app.blade.php`.

**Root Cause:**
Inertia.js defaults to rendering into a root view called `app`. The host application may not define this view (e.g., it uses `welcome` or a different layout). The package needs to tell Inertia to use its own view (`api-docs::app`).

**Fix:**
The package's `BaseController` sets `Inertia::setRootView('api-docs::app')` so all package controllers use the package's own blade template.

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Http/Controllers/BaseController.php`
- `packages/rbr/laravel-api-docs/resources/views/app.blade.php`

---

## 5. No hint path defined for [api-docs]

**Date:** 2026-03-29 | **Commit:** `43751b7`

**Error Message:**
```
InvalidArgumentException: No hint path defined for [api-docs].
```

**When:** After installing the package, the ServiceProvider is not being discovered/loaded.

**Root Cause:**
The package was not properly linked in the host app's `composer.json`. Without the ServiceProvider booting, `loadViewsFrom(..., 'api-docs')` never runs, so Laravel doesn't know the `api-docs::` view namespace.

**Fix:**

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

## 6. Inertia root view mismatch in host app

**Date:** 2026-03-29 | **Commit:** `c346526`

**Symptom:**
Package pages render inside the host app's layout instead of the package's own layout, or render with broken styles.

**When:** The host app has its own `HandleInertiaRequests` middleware that sets a different root view.

**Root Cause:**
The host app's Inertia middleware overrides the root view globally. The package needs its own middleware to set the root view specifically for package routes.

**Fix:**
Created `SetInertiaRootView` middleware that sets `Inertia::setRootView('api-docs::app')` and applied it to all package routes via route group middleware.

**Key Files:**
- `packages/rbr/laravel-api-docs/src/Http/Middleware/SetInertiaRootView.php`
- `packages/rbr/laravel-api-docs/routes/web.php` — middleware applied to route group

---

# 2026-03-30 — Host App Asset & Rendering Fixes

Issues discovered when testing the installed package in the host app (`appza-backend`). The backend worked (queries ran, data existed) but the frontend failed to render.

---

## 7. Assets Not Found (JS/CSS not loaded)

**Date:** 2026-03-30

**Error Message:**
```
Assets Not Found
The API documentation assets (JS/CSS) could not be loaded.
(Searched for: .../public/vendor/api-docs/build/manifest.json [NOT FOUND]
and .../public/build/manifest.json [NOT FOUND])
isHot: [FALSE]
```

**When:** After installing the package in a host Laravel app and visiting `/docs/api`.

**Root Cause:**
The package's frontend (React/Mantine/Inertia) must be pre-built into `packages/rbr/laravel-api-docs/public/build/`. If the build doesn't exist when `composer install` copies the package to `vendor/`, the host app has no JS/CSS to serve. The `vendor:publish --tag=api-docs-assets-build` command also fails because the source directory doesn't exist in vendor.

**Fix (final solution):**
Added `autoPublishAssets()` method in `ApiDocServiceProvider` that automatically copies pre-built assets from the package's `public/build/` to the host app's `public/vendor/api-docs/build/` on the first artisan command after install. No manual `vendor:publish` needed.

```php
// In ApiDocServiceProvider::boot()
if ($this->app->runningInConsole()) {
    $this->autoPublishAssets();
}
```

The auto-publish:
- Checks if `public/vendor/api-docs/build/manifest.json` exists — skips if already published
- Copies from `vendor/rbr/laravel-api-docs/public/build/` to `public/vendor/api-docs/build/`
- Runs only in console context (artisan commands) where file permissions allow writing
- Silently fails if permissions are insufficient (falls back to manual publish)

**Prevention:**
- Always run `npm run build` inside the package directory **before** committing or releasing
- Commit `packages/rbr/laravel-api-docs/public/build/` to git so it's always included
- The root `.gitignore` entry `/public/build` does NOT exclude the package's build (leading `/` scopes it to root only)

**Key Files:**
- `packages/rbr/laravel-api-docs/src/ApiDocServiceProvider.php` — `autoPublishAssets()` method
- `packages/rbr/laravel-api-docs/resources/views/app.blade.php` — asset detection logic
- `packages/rbr/laravel-api-docs/vite.config.js` — `outDir: 'public/build'`

---

## 8. Mantine CSS not loaded — page renders unstyled or invisible

**Date:** 2026-03-30

**Symptom:**
Page loads, React mounts, but everything is invisible or completely unstyled (no Mantine AppShell, no sidebar, no header).

**When:** Package is installed in a host app using pre-built assets (`public/vendor/api-docs/build/`).

**Root Cause:**
The Vite build splits CSS into two separate files:
1. `resources/css/app.css` → Tailwind/app styles (listed as its own manifest entry)
2. Mantine CSS (`@mantine/core/styles.css` imported in `app.jsx`) → extracted as a CSS dependency of the JS entry

The manifest shows:
```json
"resources/js/app.jsx": {
    "file": "assets/app-XXX.js",
    "css": ["assets/app-YYY.css"]  // <-- Mantine CSS (210KB) NOT being loaded!
}
```

The blade template was only loading `resources/css/app.css` from the manifest but **not** the CSS dependencies listed under the JS entry's `css` array. Without Mantine's 210KB of styles, all components render with zero dimensions — blank page.

**Fix:**
Added a `@foreach` loop in `app.blade.php` to load CSS dependencies from the JS manifest entry:
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

## 9. Blank page — Cannot read properties of undefined (reading 'startsWith')

**Date:** 2026-03-30

**Error Message (in browser console):**
```
Uncaught TypeError: Cannot read properties of undefined (reading 'startsWith')
```

**When:** Page loads with correct data from the backend (queries run, Inertia `data-page` is populated) but the screen is completely blank/white. No visible error unless you open browser DevTools console.

**Root Cause:**
`Layout.jsx` was destructuring `url` from `usePage().props`:
```jsx
// WRONG — url is NOT inside props
const { url, apiDocsConfig } = usePage().props;
```
In Inertia.js, `url` is a **top-level** property on `usePage()`, **not** inside `props`. It worked in the dev project because the dev Vite setup happened to share `url` as a prop via `HandleInertiaRequests` middleware, but host apps don't do this. When `url` is `undefined`, the `.startsWith()` call on line 9 crashes React before it can mount — resulting in a completely blank page.

**How we found it:**
Added a temporary error-catching script to the blade template:
```html
<script>
    window.addEventListener('error', function(e) {
        var d = document.createElement('div');
        d.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#fee;color:#c00;padding:1rem;z-index:99999;font-family:monospace;';
        d.textContent = 'JS Error: ' + e.message + '\n' + (e.filename || '') + ':' + (e.lineno || '');
        document.body.prepend(d);
    });
</script>
```
This revealed the exact error and line number. The script was removed after fixing.

**Fix:**
```jsx
// CORRECT — url from usePage() directly, props for shared data
const page = usePage();
const url = page.url || '';
const apiDocsConfig = page.props?.apiDocsConfig;
```

**Key Files:**
- `packages/rbr/laravel-api-docs/resources/js/Components/ApiDocs/Layout.jsx`

**Lesson:** Always use `usePage().url` for the current URL, and `usePage().props` only for server-shared data. Never assume dev-environment Inertia behavior matches host-app behavior.

---

## 10. Auto-publish fails with permission denied

**Date:** 2026-03-30

**Error Message (in Laravel log):**
```
ErrorException: mkdir(): Permission denied at ApiDocServiceProvider.php
```

**When:** First web request after installing the package. The auto-publish tries to create `public/vendor/api-docs/build/` but fails.

**Root Cause:**
The web server (Apache/Nginx) runs as `www-data` user, but the `public/` directory is owned by the developer user (e.g., `rbs-02`). The `www-data` user doesn't have permission to create new directories under `public/`.

**Fix:**
Two changes:
1. **Auto-publish only runs in console context** (`$this->app->runningInConsole()`) — artisan commands run as the developer user who has write permission
2. **Wrapped in try/catch with `@` error suppression** — if permissions still fail, it silently falls back to manual publishing instead of crashing the app

```php
// Only auto-publish during artisan commands (not web requests)
if ($this->app->runningInConsole()) {
    $this->autoPublishAssets();
}
```

**Trigger:** Any artisan command after install automatically publishes assets:
```bash
php artisan migrate          # triggers auto-publish
php artisan api-docs:generate  # triggers auto-publish
php artisan tinker            # triggers auto-publish
```

**Fallback (if auto-publish still fails):**
```bash
php artisan vendor:publish --tag=api-docs-assets-build --force
```

**Key Files:**
- `packages/rbr/laravel-api-docs/src/ApiDocServiceProvider.php` — `autoPublishAssets()` method

---

# Quick Checklist — Fresh Install in a New Host App

```bash
# 1. Require the package
composer require rbr/laravel-api-docs

# 2. Run migrations (also auto-publishes frontend assets!)
php artisan migrate

# 3. Generate documentation
php artisan api-docs:generate

# 4. Visit the docs
open http://your-app.local/docs/api
```

**That's it — 3 commands.** No manual asset publishing needed.

---

# Timeline Summary

| Date | Issue | Error | Root Cause |
|------|-------|-------|------------|
| 2026-03-17 | Inline validation not detected | "No parameters" | Parser only matched `$request->validate()` |
| 2026-03-17 | FormRequest rules not extracted | "No parameters" | Only matched `return [...]`, not `$rules = [...]` |
| 2026-03-29 | RouteScanner crash | `Undefined array key 1` | Tuple routes without `@` in action string |
| 2026-03-29 | View not found | `View [app] not found` | Host app missing default Inertia root view |
| 2026-03-29 | View namespace error | `No hint path for [api-docs]` | ServiceProvider not auto-discovered |
| 2026-03-29 | Root view mismatch | Wrong layout renders | Host middleware overrides Inertia root view |
| 2026-03-30 | Assets not found | "Assets Not Found" page | Pre-built JS/CSS not in `public/vendor/` |
| 2026-03-30 | Mantine CSS missing | Blank/unstyled page | Blade template didn't load JS entry's CSS deps |
| 2026-03-30 | Layout.jsx crash | `Cannot read 'startsWith'` | `usePage().props.url` instead of `usePage().url` |
| 2026-03-30 | Auto-publish permission | `mkdir(): Permission denied` | Web server can't write to `public/` |
