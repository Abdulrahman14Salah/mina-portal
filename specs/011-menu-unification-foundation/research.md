# Research: Menu Unification — Foundation and Architecture

**Branch**: `011-menu-unification-foundation` | **Date**: 2026-03-22

---

## Decision 1: Menu Item Storage — Config vs Database

**Decision**: PHP config array (`config/navigation.php`) for Phase 1.

**Rationale**: Navigation items are developer-controlled, version-tracked, and change infrequently. A database would add unnecessary migration overhead and a management UI for no benefit in Phase 1. Constitution Principle III (Database-Driven Workflows) applies to *visa workflow data*, not application navigation structure. The existing `config/admin-navigation.php` already proves the config pattern is effective for this codebase.

**Alternatives considered**:
- Database table — Deferred. Future enhancement (admin-configurable menus). Out of scope per spec.
- Hardcoded in Blade — Rejected. Violates Constitution Principle II (business logic in views) and FR-007.

---

## Decision 2: Role Filtering Location — MenuService (dedicated service class)

**Decision**: `App\Services\Navigation\MenuService` owns all filtering logic.

**Rationale**: Constitution Principle II forbids business logic in Blade. Constitution Principle IV requires all data retrieval through Service classes. The service reads `config/navigation.php`, filters by the authenticated user's roles using `$user->hasRole()`, and returns a clean array. No role checks appear in Blade.

**Alternatives considered**:
- Config helper directly in Blade (`@foreach(config('navigation') as $item) @if(in_array('admin', $item['roles']))`) — Rejected: business logic in view.
- Middleware injecting menu into shared view data — Rejected: over-engineering; View Composers handle this more idiomatically in Laravel.

---

## Decision 3: Injection Mechanism — View Composer

**Decision**: `App\View\Composers\MenuComposer` registered in `AppServiceProvider`, composing `components.admin.nav`, `components.client.nav`, and `components.reviewer.nav` views.

**Rationale**: The `AdminBreadcrumbComposer` already establishes this pattern in `AppServiceProvider`. View Composers inject `$menuItems` automatically before the Blade component renders, keeping the component template clean (no service calls inside `@php` blocks). Consistent with existing codebase conventions.

**Alternatives considered**:
- `app(MenuService::class)` called inside Blade component — Would work, but `@php` blocks in views approach business logic (mild violation of Principle II). View Composer is cleaner.
- Global `View::share('menuItems', ...)` in middleware — Rejected: runs on every request including non-navigation views; wasteful.

---

## Decision 4: Active State Detection — Route Pattern Matching

**Decision**: `request()->routeIs($item['active_pattern'])` using wildcard patterns stored per item in the config.

**Rationale**: Already working perfectly in `config/admin-navigation.php` (e.g., `admin.applications.*` highlights Applications for all sub-pages). No new mechanism needed — extend the same pattern to reviewer and client items.

**Alternatives considered**:
- URL prefix matching (`str_starts_with(request()->path(), ...)`) — Rejected: less precise, breaks with route name changes.
- Current route name exact match — Rejected: doesn't cover sub-routes (US3 acceptance scenario 2).

---

## Decision 5: Translation Key Organization — Dedicated `navigation.php` Lang File

**Decision**: Create `resources/lang/en/navigation.php` and `resources/lang/ar/navigation.php` for all nav item labels. Existing `admin.nav_*` keys in `admin.php` remain untouched (backward compatible).

**Rationale**: A single `navigation.php` lang file creates a clear, discoverable home for all nav labels. Existing `admin.nav_*` keys in `admin.php` are preserved to avoid breaking the existing admin nav during transition; the unified config will reference `navigation.*` keys for all items going forward. Constitution Principle X requires Arabic translations.

**Alternatives considered**:
- Reuse existing lang files (`admin.nav_*`, `reviewer.nav_*`, `client.nav_*`) — Acceptable, but scatters navigation labels across multiple files. A dedicated file is cleaner.
- Move `admin.nav_*` to `navigation.php` — Rejected for Phase 1: would require updating all existing label_key references and breaks the backward-compatibility constraint (FR-008).

---

## Decision 6: Layout Strategy — Keep Separate Role Layouts, Share Menu Component

**Decision**: The three role-specific layout components (`admin-layout.blade.php`, `reviewer-layout.blade.php`, `client-layout.blade.php`) remain separate. Each includes `<x-nav.main />` — a new shared Blade component backed by the MenuService.

**Rationale**: Confirmed by clarification Q1 (Option B). FR-008 mandates zero regression on existing layouts. Separate layouts allow role-specific header content (breadcrumbs for admin, application reference for client). Only the nav *component* is unified.

---

## Decision 7: Client Tab Nav — Included in Unified Config as Parameterized Items

**Decision**: Client dashboard tabs (overview, documents, tasks, payments, etc.) are included in `config/navigation.php` as client-role items with `route_params` to pass the `tab` query parameter to `client.dashboard`.

**Rationale**: Centralizing all navigation in one place (FR-001) includes the client tab navigation. The config item structure supports an optional `route_params` array, enabling `route('client.dashboard', ['tab' => 'documents'])`. The existing client nav component is refactored to use `$menuItems` instead of its inline array.

**Alternatives considered**:
- Exclude client tabs from unified nav — Rejected: violates FR-001 (all items in one place) and US2.
- Keep client tabs inline in Blade — Rejected: violates FR-007 (no role logic in Blade).

---

## Existing State Summary (Codebase Audit)

| Role | Current Nav Implementation | Migration Needed |
|------|---------------------------|-----------------|
| Admin | `config('admin-navigation')` read directly in Blade | Update to use MenuService via Composer; keep config structure |
| Reviewer | None — layout shows title only | Create `components/reviewer/nav.blade.php`; update reviewer layout |
| Client | Inline PHP array in `components/client/nav.blade.php` | Migrate to config; update component to use `$menuItems` |

**Current admin nav items**: Dashboard, Applications, Visa Types, Clients, Task Builder, Reviewers, Users (7 items)
**Current reviewer nav items**: Dashboard, Applications Queue (2 items — derived from routes)
**Current client nav items**: Overview, Documents, Tasks, Payments, Timeline, Messages, Profile, Support (8 tabs)
