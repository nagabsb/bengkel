# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AutoServ is a Laravel 12 multi-tenant SaaS for auto repair workshops (bengkel) in Indonesia. The stack is Laravel (PHP 8.3) + Inertia.js + Vue 3 + Tailwind CSS 4 + Vite. The app is localized in Indonesian (`APP_LOCALE=id`).

## Commands

```bash
# First-time setup
composer setup

# Development (runs Laravel server + queue + Vite concurrently)
composer dev

# Run all tests
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run a specific test by filter
php artisan test --filter=TestName

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Clear config cache (required before tests)
php artisan config:clear
```

## Architecture

### Multi-Tenant Model

The data hierarchy is: **Tenant → Workshop(s) → Users/Customers/Data**.

- A **Tenant** is a business account (the paying customer of the SaaS).
- A Tenant can have multiple **Workshops** (physical branches).
- The active workshop is tracked per-session via `OwnerWorkshopSwitcherService`.
- Primary keys across the app use **ULIDs** (`HasUlids` trait on most models).

### Three Access Scopes

| Scope | Route prefix | Permission prefix | Who |
|---|---|---|---|
| Platform (superadmin) | `/platform/*` | `platform.*` | SaaS operator |
| Owner (tenant user) | `/owner/{tenant}/*` | `customers.view`, `service_orders.manage`, etc. | Workshop owners/staff |
| Public | `/booking`, `/estimate-approval/*` | — | End customers |

### TenantContext

`App\Support\Tenant\TenantContext` is a singleton injected across services. It is populated by the `SetTenantContext` middleware, which resolves the tenant from either the `{tenant}` URL segment or the request subdomain. All owner-scoped queries must use the tenant ID from this context to scope data correctly.

The `owner.menu.access` middleware (`EnsureOwnerMenuAccess`) additionally enforces plan-based menu visibility per tenant.

### Request Lifecycle (Owner scope)

```
Route → SetTenantContext middleware → EnsureOwnerMenuAccess → can:permission → Controller → Service → Response
```

Controllers in `app/Http/Controllers/Owner/` are thin — they validate via FormRequest classes in `app/Http/Requests/Owner/` and delegate all business logic to `app/Services/Owner/`.

### AI Integration

The platform supports multiple AI providers (OpenAI, Anthropic, Gemini, Groq, Mistral, DeepSeek, Kimi) configured via `PlatformAiSetting`. The `PlatformAiAgentService` handles provider failover.

AI prompts follow a four-layer composition (in order):
1. **System prompt** (global, set by platform superadmin in `PlatformAiPromptSetting`)
2. **Feature prompt** (per-feature, e.g., `service_estimate_v1`)
3. **Tenant override** (tenant-configurable tone/preferences in `TenantAiPromptOverride`)
4. **Runtime input** (live order/vehicle/customer data)

Tenants cannot override the output format, safety rules, or mandatory disclaimers. All AI generations are logged to `ServiceOrderEstimateAiLog` and `AiRuntimeLog` as audit trails.

### Frontend

Vue 3 SPA via Inertia.js. State management uses Pinia (`resources/js/Stores/`). Frontend is organized into:
- `resources/js/Pages/` — Inertia page components (mirrors backend route structure: `Owner/`, `Platform/`, etc.)
- `resources/js/Components/` — reusable UI components
- `resources/js/Layouts/` — page shell layouts
- `resources/js/Composables/` — Vue composables
- `resources/js/Services/` — API/axios helpers
- `resources/js/Utils/` — utility functions

### Permissions (Spatie)

Owner-scope permissions follow the pattern `resource.action`, e.g., `customers.view`, `service_orders.manage`, `invoice_payments.manage`. Platform-scope permissions use `platform.tenants.view`, `platform.billing.manage`, etc. Permissions are seeded via migrations (not seeders) to be version-controlled and applied in order.

### Testing

Tests use Pest 4 with Laravel plugin. The test environment uses in-memory SQLite (`DB_DATABASE=:memory:`). Feature tests are in `tests/Feature/`, unit tests in `tests/Unit/`.
