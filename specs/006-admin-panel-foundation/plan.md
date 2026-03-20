# Implementation Plan: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation` | **Date**: 2026-03-20 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/006-admin-panel-foundation/spec.md`

---

## Summary

Build the structural foundation for the Phase 6 admin panel: a persistent navigation shell, an admin dashboard home with independently-failing summary cards, role-gated access (admin only), and a reusable shared list view pattern (search-on-submit, sort-by-column, 15-per-page pagination). No new database tables are required. The existing Applications and Users list views are refactored to conform to the new shared pattern.

---

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade SSR), Alpine.js v3, `spatie/laravel-permission` v6+
**Storage**: MySQL (MAMP local dev, port 8889); SQLite in-memory (tests)
**Testing**: PHPUnit via `php artisan test`
**Target Platform**: Web application, server-side rendered
**Project Type**: Web application (MVC, Blade SSR)
**Performance Goals**: Admin dashboard home renders in ≤2 seconds (SC-003); individual widget failures do not degrade load time
**Constraints**: Admin-only access enforced via `dashboard.admin` permission; all strings via `__()` (constitution Principle X); no Livewire, no SPA framework
**Scale/Scope**: Small-medium portal; paginated lists at 15 records/page; no high-throughput requirements

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | ✅ Pass | Admin module already exists (`App\Http\Controllers\Admin\`, `App\Services\Admin\`). All new code lives within it. |
| II. Separation of Concerns | ✅ Pass | `AdminDashboardService` owns data retrieval + widget error handling. Controllers handle HTTP only. Blade views render only. |
| III. Database-Driven Workflows | ✅ Pass | N/A for foundation layer. Task Builder (sub-spec) will enforce this when built. |
| IV. API-Ready Design | ✅ Pass | All data flows through `AdminDashboardService`. Controllers return structured arrays to views. No direct Eloquent in controllers. |
| V. Roles & Permissions | ✅ Pass | `dashboard.admin` permission enforced via middleware on all admin routes. `$user->can()` / Policy pattern used throughout. |
| VI. Payment Integrity | ✅ N/A | Not applicable to this feature. |
| VII. Secure Document Handling | ✅ N/A | Not applicable to this feature. |
| VIII. Dynamic Workflow Engine | ✅ N/A | Not applicable to foundation layer. |
| IX. Security by Default | ✅ Pass | All admin routes behind `auth` + `verified` + `can:dashboard.admin`. Form Requests added for any state-changing inputs. |
| X. Multi-Language Support | ⚠️ Resolved | Original spec assumption "English-only Phase 6" OVERRIDDEN by constitution. All admin Blade strings MUST use `__('admin.*')`. Arabic translation values deferred to Phase 9 (stub with English fallbacks). Lang files created at `lang/en/admin.php` and `lang/ar/admin.php`. |
| XI. Observability & Activity Logging | ✅ Pass | FR-019–FR-021: all significant admin actions logged via existing `AuditLogService`. Standard event key table defined in `research.md`. |
| XII. Testing Standards | ✅ Pass | `AdminDashboardTest` covers access control, widget failure, search, sort, and pagination. |

**Complexity Tracking**: No violations requiring justification.

---

## Project Structure

### Documentation (this feature)

```text
specs/006-admin-panel-foundation/
├── plan.md              ← this file
├── spec.md              ← feature specification
├── research.md          ← Phase 0 research output
├── data-model.md        ← Phase 1 data model
├── quickstart.md        ← Phase 1 dev quickstart
├── contracts/
│   └── routes.md        ← Phase 1 route contracts
├── checklists/
│   └── requirements.md  ← spec quality checklist
└── tasks.md             ← Phase 2 output (/speckit.tasks — not yet created)
```

### Source Code Layout

```text
app/
├── Http/
│   └── Controllers/
│       └── Admin/
│           ├── DashboardController.php       ← NEW
│           ├── ApplicationController.php     ← EXISTS (keep, update)
│           ├── DocumentController.php        ← EXISTS (keep)
│           ├── PaymentController.php         ← EXISTS (keep)
│           ├── UserController.php            ← EXISTS (keep, update)
│           ├── VisaTypeController.php        ← NEW (stub only)
│           ├── ClientController.php          ← NEW (stub only)
│           ├── TaskBuilderController.php     ← NEW (stub only)
│           └── ReviewerController.php        ← NEW (stub only)
├── Services/
│   └── Admin/
│       └── AdminDashboardService.php         ← NEW
├── View/
│   └── Composers/
│       └── AdminBreadcrumbComposer.php       ← NEW
config/
└── admin-navigation.php                      ← NEW
resources/
└── views/
    ├── admin/
    │   ├── dashboard.blade.php               ← NEW
    │   ├── placeholder.blade.php             ← NEW (shared stub for upcoming sections)
    │   ├── applications/
    │   │   └── index.blade.php               ← EXISTS (refactor to shared pattern)
    │   └── users/
    │       └── index.blade.php               ← EXISTS (refactor to shared pattern)
    └── components/
        └── admin/
            ├── nav.blade.php                 ← NEW
            ├── table.blade.php               ← NEW
            ├── dashboard-card.blade.php      ← NEW
            └── breadcrumb.blade.php          ← NEW
lang/
├── en/
│   └── admin.php                             ← NEW
└── ar/
    └── admin.php                             ← NEW (English stubs, Phase 9 Arabic)
tests/
└── Feature/
    └── Admin/
        └── AdminDashboardTest.php            ← NEW
```

**Modified files**:
- `routes/web.php` — add admin dashboard route + placeholder section routes
- `app/Providers/AppServiceProvider.php` — register `AdminBreadcrumbComposer` for `admin.*` views
- `resources/views/admin/applications/index.blade.php` — refactor to `<x-admin.table>` component
- `resources/views/admin/users/index.blade.php` — refactor to `<x-admin.table>` component (if not already compliant)

**Structure Decision**: Single project, Laravel MVC. Admin module is `app/Http/Controllers/Admin/` + `app/Services/Admin/`. Blade components live in `resources/views/components/admin/`. No backend/frontend split.

---

## Phase 0: Research

✅ **Complete** — see `research.md` for all decisions.

**Key decisions from research**:

1. **Navigation**: Blade component + `config/admin-navigation.php` array. Single config file is the only edit needed to add a nav item.
2. **Widget failure**: `AdminDashboardService::loadSafely(callable)` returns `['data', 'error']`; controller passes per-widget results to view; `<x-admin.dashboard-card>` renders inline error state.
3. **List table**: `<x-admin.table>` Blade component, server-side search (submit-on-enter), query-string sort, `paginate(15)`, `@forelse` empty state.
4. **Audit logging**: Explicit `AuditLogService::log()` calls from Service layer — consistent with existing codebase pattern.
5. **Breadcrumbs**: Per-controller `$breadcrumbs` array + `AdminBreadcrumbComposer` default + `<x-admin.breadcrumb>` component.
6. **Multi-language**: `lang/en/admin.php` + `lang/ar/admin.php` created now. All Blade strings use `__('admin.*')`. Arabic content deferred to Phase 9.

---

## Phase 1: Design & Contracts

✅ **Complete** — see `data-model.md`, `contracts/routes.md`, `quickstart.md`.

### Data Model Summary

**No new migrations.** Foundation uses existing tables:
- `visa_applications` (dashboard counts + recent list)
- `users` (client count)
- `audit_logs` (admin action recording)

New non-database structures:
- `config/admin-navigation.php` — nav item config array
- Widget result shape: `['data' => mixed|null, 'error' => string|null]`
- Breadcrumb item shape: `['label' => string, 'route' => string|null]`

New lang file keys in `lang/en/admin.php` (full list in `data-model.md`).

### Route Contract Summary

New routes:
- `GET /admin/dashboard` → `Admin\DashboardController@index` → `admin.dashboard`
- `GET /admin/visa-types` → `Admin\VisaTypeController@index` → `admin.visa-types.index` (stub)
- `GET /admin/clients` → `Admin\ClientController@index` → `admin.clients.index` (stub)
- `GET /admin/task-builder` → `Admin\TaskBuilderController@index` → `admin.task-builder.index` (stub)
- `GET /admin/reviewers` → `Admin\ReviewerController@index` → `admin.reviewers.index` (stub)

All admin routes: `middleware(['auth', 'verified'])`, `prefix('admin')`, `name('admin.')`, `can:dashboard.admin`.

### Blade Component Contracts

| Component | Props | Renders |
|-----------|-------|---------|
| `<x-admin.nav>` | none (reads config + route) | Sidebar/top nav with active state |
| `<x-admin.breadcrumb :items="$breadcrumbs">` | `items: array` | Breadcrumb trail |
| `<x-admin.dashboard-card :widget="$w" :title="..." :href="...">` | `widget: array, title: string, href: string\|null` | Summary count card or error state |
| `<x-admin.table :columns :rows :pagination :search-query :sort-by :sort-dir>` | See contracts/routes.md | Searchable, sortable, paginated table |

### Admin Layout Shell

The admin layout is `resources/views/layouts/app.blade.php` (already exists, uses `<x-app-layout>`). The admin panel uses the same Breeze layout shell. The `<x-admin.nav>` component is included in the admin layout via the header slot or a dedicated admin shell layout (to be determined during implementation — either extend the existing Breeze layout or create a dedicated `admin-layout` component).

> **Implementation note**: Prefer creating a dedicated `resources/views/components/admin-layout.blade.php` that wraps `<x-app-layout>` with the admin nav and breadcrumb. This keeps the admin shell self-contained and avoids polluting the generic layout.

---

## Constitution Check — Post-Design

| Principle | Post-Design Status | Notes |
|-----------|--------------------|-------|
| X. Multi-Language | ✅ Resolved | `lang/en/admin.php` and `lang/ar/admin.php` created; all Blade strings use `__('admin.*')` |
| All others | ✅ Pass | No new violations introduced by design decisions |

---

## Testing Plan

### AdminDashboardTest

| Test case | Verifies |
|-----------|---------|
| `admin_can_view_dashboard` | 200, breadcrumb present |
| `client_is_forbidden_from_dashboard` | 403 |
| `reviewer_is_forbidden_from_dashboard` | 403 |
| `unauthenticated_is_redirected_to_login` | 302 → /login |
| `dashboard_shows_pending_count` | Summary card renders count |
| `dashboard_card_shows_error_when_data_unavailable` | "Unable to load" rendered (mock service to throw) |
| `dashboard_shows_recent_applications` | 5 most recent apps listed |
| `admin_can_view_all_placeholder_sections` | All 5 stub routes return 200 |
| `applications_list_searchable` | `?search=X` filters results |
| `applications_list_sorted_by_newest_default` | Default order = `created_at desc` |
| `applications_list_paginated_at_15` | 15 per page, pagination links present |

### Existing tests

All existing admin tests (`AdminPaymentTest`, `DocumentAdminTest`, `UserManagementTest`) must continue to pass after the refactoring of applications/users list views.
