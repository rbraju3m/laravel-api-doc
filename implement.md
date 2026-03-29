# Implementation Log

This document tracks all significant changes made to the Laravel API Documentation Generator.

---

## 2026-03-17: Auto-Generate API Docs from External Laravel Projects

### Summary

Added the ability to point to an external Laravel project on the filesystem, click "Generate Docs", and have its routes/controllers/FormRequests auto-scanned — the same way the local project works. Uses `php artisan route:list --json` in the external project directory and parses controller/FormRequest PHP files via regex.

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_17_000000_add_project_path_to_api_projects.php` | Adds nullable `project_path` column to `api_projects` table |
| `app/Services/ApiDoc/ExternalProjectScanner.php` | Runs `php artisan route:list --json` in external project, parses output into route array |
| `app/Services/ApiDoc/ExternalControllerParser.php` | Reads controller PHP files from external project, extracts docblocks, FormRequest type-hints, and URI parameters via regex |
| `app/Services/ApiDoc/ExternalRequestParser.php` | Reads FormRequest PHP files from external project, extracts `rules()` method content via regex, maps to parameter types |

### Modified Files

| File | Change |
|------|--------|
| `app/Models/ApiProject.php` | Added `project_path` to `$fillable` |
| `app/Services/ApiDoc/DocGenerator.php` | Added `generateForProject(ApiProject $project)` method; injected 3 new external parser services |
| `app/Http/Controllers/ExternalProjectController.php` | Added `generate()` action — validates project path, calls `DocGenerator::generateForProject()` |
| `app/Http/Requests/StoreProjectRequest.php` | Added `project_path` validation rule; made `base_url` nullable |
| `app/Http/Requests/UpdateProjectRequest.php` | Added `project_path` validation rule; made `base_url` nullable |
| `routes/web.php` | Added `POST /docs/api/projects/{project}/generate` route |
| `app/Console/Commands/GenerateApiDocs.php` | Added `--project={id}` option for generating docs for a specific external project |
| `resources/js/Pages/ApiDocs/Projects/Create.jsx` | Added "Laravel Project Path" input field |
| `resources/js/Pages/ApiDocs/Projects/Edit.jsx` | Added "Laravel Project Path" input field |
| `resources/js/Pages/ApiDocs/Projects/Show.jsx` | Added "Generate Docs" button (visible when `project_path` is set) with loading state |

### Design Decisions

- **`php artisan route:list --json`**: Chosen over loading external code into our autoloader. Cleanest approach — works with any Laravel version, no memory conflicts.
- **Regex-based file parsing**: External controllers and FormRequests are parsed via regex/token matching since we can't autoload their classes. Resolves PSR-4 class paths via `composer.json` autoload config.
- **Truncate-and-rebuild per project**: `generateForProject()` only deletes groups belonging to that specific project (`where('api_project_id', $project->id)`), leaving other projects and local docs untouched.
- **Validation guards**: The `generate()` controller action validates that the path exists, is a directory, and contains an `artisan` file before attempting generation.

### Routes Added

| Method | URI | Purpose |
|--------|-----|---------|
| POST | `/docs/api/projects/{project}/generate` | Trigger doc generation from external Laravel project |

### CLI Usage

```bash
# Generate docs for a specific external project by ID
php artisan api-docs:generate --project=1
```

### Verification

- Migration ran successfully
- All 13 existing tests pass (no regressions)
- PHP linting passed (`./vendor/bin/pint`)
- Frontend build succeeded (`npm run build`)

---

## 2026-03-17: Rich Payload, Parameters & Response Examples for External Projects

### Summary

Enhanced external project doc generation to produce realistic request payloads with example values and probable output responses based on actual model fields, instead of generic placeholders like `{"data": "..."}`.

### Problem

Previously, external project docs showed:
- Generic response bodies: `{"data": "..."}` for GET, `{"message": "Resource created successfully."}` for POST
- Only FormRequest parameters — missed inline `$request->validate([...])` rules
- No model-aware response structure

### New Files

| File | Purpose |
|------|---------|
| `app/Services/ApiDoc/ExternalResponseAnalyzer.php` | Analyzes controller methods to detect models, reads model `$fillable`/`$casts` and migration columns, generates realistic response bodies with proper field types and example values |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/ApiDoc/ExternalControllerParser.php` | Added `extractInlineValidation()` — captures `$request->validate([...])` rules when no FormRequest is used; added `extractBraceBlock()` helper for parsing method bodies |
| `app/Services/ApiDoc/DocGenerator.php` | Injected `ExternalResponseAnalyzer`; added `convertInlineRulesToParams()` and `generateInlineExample()` for inline validation; wired analyzer into `generateForProject()` |

### How It Works

**Request Payload & Parameters:**
1. FormRequest `rules()` — parsed from external file via regex (existing)
2. Inline `$request->validate([...])` — NEW: parsed from the controller method body when no FormRequest is type-hinted
3. Both produce parameters with name, type, required flag, validation rules, and context-aware example values

**Response Body Generation (ExternalResponseAnalyzer):**
1. Detects the model used in the controller method by analyzing:
   - `Model::create()`, `Model::find()`, `Model::paginate()` calls
   - `$model->save()`, `$model->update()` patterns with type-hinted parameters
   - `new Model()` instantiations
   - Falls back to guessing from URI (e.g., `/api/users` → `User`)
2. Reads the model file to extract `$fillable` fields and `$casts` type mappings
3. Reads the migration file (matches by table name convention) to get column types
4. Generates example values based on:
   - **Field name patterns:** `email` → `john@example.com`, `price` → `29.99`, `is_active` → `true`, `avatar` → URL
   - **Field type:** integer → `1`, boolean → `true`, datetime → ISO timestamp
   - **Suffixes:** `*_id` → `1`, `*_at` → timestamp, `*_url` → URL
5. Shapes response structure by HTTP method and controller method name:
   - `GET index` → paginated list: `{"data": [...], "meta": {"current_page": 1, ...}}`
   - `GET show` → single resource: `{"data": {fields...}}`
   - `POST store` → 201 with created resource: `{"data": {fields...}}`
   - `PUT/PATCH update` → updated resource: `{"data": {fields...}}`
   - `DELETE destroy` → `{"message": "User deleted successfully."}`
6. Adds contextual error responses:
   - 422 with first field name in error message (for validated endpoints)
   - 404 for show/update/delete endpoints with URI parameters
   - 401 for authenticated endpoints

### Example Output

For a `POST /api/users` endpoint with a `StoreUserRequest` containing `name`, `email`, `password` rules and a `User` model with `$fillable = ['name', 'email', 'password']`:

**Parameters:**
| Name | Location | Type | Required | Example |
|------|----------|------|----------|---------|
| name | body | string | yes | John Doe |
| email | body | string (email) | yes | user@example.com |
| password | body | string | yes | secret123 |

**201 Response:**
```json
{
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2026-01-15T10:30:00.000000Z",
        "updated_at": "2026-01-15T10:30:00.000000Z"
    }
}
```

**422 Response:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

### Verification

- All 13 existing tests pass (no regressions)
- PHP linting passed (`./vendor/bin/pint`)
- Frontend build succeeded (`npm run build`)

---

## 2026-03-17: Example Request Body JSON & Parameter Descriptions

### Summary

Added an "Example Request Body" JSON block to the endpoint detail page and added a Description column to the parameters table. Previously, body parameters were only shown as individual rows in a table — now there's also a combined JSON block showing the full request payload.

### New Files

| File | Purpose |
|------|---------|
| `resources/js/Components/ApiDocs/RequestBodyExample.jsx` | Component that builds a combined JSON example from body parameters, converts types properly (string→string, integer→number, boolean→bool), and renders with CodeHighlight |

### Modified Files

| File | Change |
|------|--------|
| `resources/js/Components/ApiDocs/ParameterTable.jsx` | Added "Description" column to the parameters table |
| `resources/js/Pages/ApiDocs/Show.jsx` | Added "Example Request Body" section between Parameters and Responses (only for POST/PUT/PATCH with body params) |
| `app/Services/ApiDoc/ExternalRequestParser.php` | Added `generateDescription()` — creates human-readable descriptions from field name + rule constraints (max chars, unique, in:values, confirmed) |
| `app/Services/ApiDoc/DocGenerator.php` | Added `generateInlineDescription()` for inline validation params; added `description` field to `convertInlineRulesToParams()` output |

### What the Endpoint Detail Page Now Shows

For a `POST /api/users` endpoint:

1. **Parameters Table** — Name, Location, Type, Required, Description, Rules, Example
2. **Example Request Body** (NEW) — Combined JSON:
   ```json
   {
     "name": "John Doe",
     "email": "user@example.com",
     "password": "secret123"
   }
   ```
3. **Responses** — 201 Created with realistic model data, 422 Validation Error, 404 Not Found, 401 Unauthenticated (as applicable)

### Verification

- All 13 existing tests pass
- PHP linting passed
- Frontend build succeeded

---

## 2026-03-17: Fix Inline Validation Parsing & URI Parameter Detection

### Problem

External project docs showed "No parameters" for controllers using:
- `$this->validate($request, [...])` (ValidatesRequests trait) — regex only matched `$request->validate([...])`
- Custom validation messages as second array `[...], [...]` — broke bracket matching
- Untyped route parameters like `$plugin` — weren't detected as URI params

### Root Cause (from `LeadController@store`)

```php
$this->validate($request, [
    'first_name' => 'required',
    'email' => 'required|email',
], [
    'first_name.required' => 'First name is required.',
]);
```

The old regex `$request->validate([...])` and `->validate([...])` didn't match `$this->validate($request, [...])`. And the greedy `.*?` inside `\[...\]` matched across the rules array into the messages array.

### Fix

**`app/Services/ApiDoc/ExternalControllerParser.php`** — Rewrote inline validation extraction:
- Added `findValidationRulesArray()` — tries 4 patterns: `$request->validate([`, `$this->validate($request, [`, `Validator::make($data, [`, `->validate([`
- Added `extractBracketBlock()` — balanced bracket parser that correctly stops at the matching `]`, ignoring nested brackets
- Added `parseRulesArray()` — extracted shared logic for parsing `'field' => 'rules'` entries
- Updated `extractUriParameters()` to accept `$uri` parameter
  - Detects untyped params (no type-hint) from method signature
  - Maps them to URI placeholders `{product}` by position
  - Falls back to URI placeholder names for any unmatched placeholders

**`app/Services/ApiDoc/DocGenerator.php`** — Now passes `$route['uri']` to `externalControllerParser->parse()`

### Result (LeadController@store)

Before: "No parameters", generic "201 Created" response

After:
- **Parameters**: `product` (uri), `first_name` (body, required), `last_name` (body, required), `email` (body, email, required), `domain` (body, url, required)
- **201 Response**: Realistic Lead model data from `$fillable` fields
- **422 Response**: Validation error with `first_name` field
- **Example Request Body** JSON block on detail page

### Verification

- All 13 tests pass
- Regenerated 217 endpoints in 17 groups for external project
- PHP lint clean, frontend build succeeded

---

## 2026-03-17: Fix FormRequest Rules Extraction for Variable-Based Patterns

### Problem

Most FormRequests in the external project (16 out of 21) use `$rules = [...]; return $rules;` pattern instead of `return [...]` directly. The parser only matched `return [...]`, causing most endpoints to show "No parameters".

Before fix: 95 endpoints with params (POST/PUT/PATCH: ~30 with params)
After fix: 115 endpoints with params (POST/PUT/PATCH: 65 out of 78 with params)

### Patterns Now Supported

| Pattern | Example | Status |
|---------|---------|--------|
| `return [...]` | `return ['name' => 'required'];` | Already worked |
| `$rules = [...]; return $rules;` | Most common in this project | **Fixed** |
| `$rules = [...]; $rules = array_merge($rules, [...]);` | AddonRequest | **Fixed** |
| `'field' => $this->cond() ? 'required\|string' : 'string'` | Ternary rules | **Fixed** |
| `$this->validate($request, [...])` | ValidatesRequests trait | Already worked |
| `Validator::make($request->all(), [...])` | Manual validator | Already worked |

### Root Cause

`ExternalRequestParser::extractRules()` used `return\s*\[...\]\s*;` regex which only matched direct `return [...]` statements. The rewrite uses balanced bracket parsing to find ALL array literals in the rules() method body.

### Fix

**`app/Services/ApiDoc/ExternalRequestParser.php`** — Complete rewrite of `extractRules()`:
- New `findAllArrayRules()` — scans the entire method body for array literals in rules context (after `=`, `return`, `merge(`)
- New `extractBracketBlock()` — balanced `[`/`]` parser for correct bracket matching
- New `extractBraceBlock()` — balanced `{`/`}` parser for method body extraction
- New `parseFieldRules()` — handles string rules, array rules, and ternary expressions
- New `isInsideString()` — skips array brackets inside string literals

### Remaining Unmatched (13 POST/PUT/PATCH endpoints)

These are legitimate cases with no extractable validation:
- Vendor controllers (`_boost`, `_debugbar`) — source not accessible
- Controllers calling `$this->validationRules()` from a separate method
- Endpoints with no validation (sort updates, login/logout)

### Verification

- All 13 tests pass
- 217 endpoints, 115 with params (up from 95)
- POST/PUT/PATCH coverage: 83% (65/78) with params
- PHP lint clean, frontend build succeeded
