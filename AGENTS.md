# AGENTS.md – MitarbeiterBoard

## Overview

Laravel 10 school intranet ("MitarbeiterBoard") for the Evangelisches Schulzentrum Radebeul. German-language domain (models, views, config). PHP 8.1+, MySQL/MariaDB, Vite + TailwindCSS v4, Alpine.js, Livewire 4, Bootstrap 4 (legacy).

## Architecture

- **Monolith** – single Laravel app, server-rendered Blade views, no SPA. Alpine.js handles interactive UI (diagnostics, wochenplan, sidebar).
- **Dual CSS** – Legacy pages use Bootstrap 4 (`public/css/`). Newer modules (diagnostics, paedDiary, wochenplan, rooms, sidebar) use Tailwind v4 via Vite. Tailwind preflight is **disabled** (`corePlugins.preflight: false`) to avoid Bootstrap conflicts. Tailwind is scoped via wrapper classes (e.g. `.diagnostic-wrapper`).
- **Vite entrypoints** are per-module, not a single bundle – see `vite.config.js`. Views load assets with `@vite()` and `@push('css')`/`@push('js')`.
- **Authorization** uses `spatie/laravel-permission` (RBAC). Permissions are checked via route middleware (`permission:view wiki`) and in controllers. Policies exist only for `DiagnosticSession`, `DiagnosticArea`, `GradingDocumentationSession`.
- **SSO** via SAML2 (`aacotroneo/laravel-saml2`) and Keycloak (`socialiteproviders/keycloak`). Local auth can be toggled via `AUTH_LOCAL` env.
- **Dashboard** is card-based, powered by View Composers (`app/View/Composers/`) registered in `ViewServiceProvider`. Each card is a Blade partial.

## Key Directories

| Path | Purpose |
|---|---|
| `app/Models/personal/` | HR models (Roster, Holiday, Employment, Timesheet, WorkingTime) |
| `app/Models/Wochenplan/` | Weekly lesson plan models (WpPlan, WpFach, WpAufgabe, WpFormatvorlage) |
| `app/Models/Inventory/` | Inventory management (Items, Location, Lieferant) |
| `app/Services/` | Business logic – `AutoRosterPlanner`, `DiagnosticService`, `Wochenplan/Wp*Service` |
| `app/Observers/` | Side effects on model events (notifications, recalculations) |
| `app/Http/Requests/` | Form Request validation; note inconsistent naming (PascalCase + camelCase) |
| `config/config.php` | Central project config (auth modes, absence types, school year start, meeting defaults) |
| `app/helpers.php` | Global helpers (`redirectBack()`, `workdays()`, `is_holiday()`, `money()`) – autoloaded via composer.json |

## Conventions

- **Language**: All UI text, model names, comments, and config keys are in **German** (e.g. `Schueler`, `Klasse`, `Vertretung`, `Wochenplan`, `Meldung`). Keep this convention.
- **Flash messages** use `session('Meldung')` with `session('type')` for alert style. Use the `redirectBack()` helper.
- **Namespace `personal`** is lowercase: `App\Models\personal\*`, `App\Http\Controllers\Personal\*` (controller namespace differs – PascalCase).
- **Settings** are stored in a `settings` DB table via `App\Models\Setting` (module, setting, value). Access centrally, not via `.env` for runtime config.
- **Permissions** are string-based: `'view wiki'`, `'edit vertretungen'`, `'create roster'`, `'has holidays'`, etc. New features must register permissions in their migration.

## Testing

```bash
php artisan test                 # Run all (PHPUnit, SQLite in-memory)
php artisan test --filter=Unit   # Unit tests only
```

- Tests use `RefreshDatabase` (see `TestCase.php`). Base `TestCase` calls `Http::preventStrayRequests()` – tests needing HTTP must call `Http::fake()` explicitly.
- Use `actingAsWithPermission('perm1', 'perm2')` from `TestCase` to create and authenticate a user with specific permissions.
- Use `Tests\Traits\CreatesTestData` for factories of complex domain objects (departments, rosters, diagnostic sessions, wochenplan).
- Factories exist in `database/factories/` for most models. Personal & Wochenplan factories are in subdirs.

## Build & Deploy

```bash
npm run build          # Vite production build (npm run build:vite)
npm run dev:vite       # Vite dev server with HMR
composer install       # PHP dependencies
php artisan migrate    # DB migrations
```

- **Deploy**: `./deploy.sh` (git pull → composer install → migrate → cache:clear).
- **Scheduler**: Runs mail reminders, ticket auto-close, recurring theme creation, timesheet mails – see `app/Console/Kernel.php`.
- **Queue**: Required for mail/notifications: `php artisan queue:work`.

## External Integrations

- **Nextcloud Talk** – `NextcloudTalkService` for chat integration
- **Google Calendar** – `spatie/laravel-google-calendar` for room booking feeds
- **Sentry** – error tracking (`sentry/sentry-laravel`)
- **wkhtmltopdf / DomPDF / Snappy** – PDF generation (roster exports, protocols, wochenplan)
- **PHPWord** – Word document generation (protocols, wochenplan)
- **Maatwebsite Excel** – Import/Export (users, students, inventory, absences)

## Common Patterns

- **New module checklist**: Model → Migration (with permission insert) → Controller → Form Request → Blade views → Route registration in `routes/web.php` (wrap with `permission:` middleware) → optional View Composer + dashboard card.
- **Observers** trigger notifications and recalculations. When adding model lifecycle hooks, add an Observer in `app/Observers/` and register it in `EventServiceProvider::boot()`.
- **Exports/Imports** follow Maatwebsite Excel patterns in `app/Exports/` and `app/Imports/`.
- **Scheduled tasks** are registered in `Console/Kernel.php` using `$schedule->call('Controller@method')` – not as separate commands.

