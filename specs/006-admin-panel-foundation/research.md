# Research: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation`
**Date**: 2026-03-20
**Status**: Complete — all unknowns resolved

---

## 1. Admin Navigation Pattern

**Decision**: Blade component (`<x-admin-nav>`) driven by a PHP config array (`config/admin-navigation.php`).

**Rationale**: The config array is the single location that controls which sections appear in the nav (satisfies FR-018: adding a section requires only a route entry + one array item, zero template changes). Active state is computed via `request()->routeIs()` inside the component, keeping the check co-located with the rendering logic.

**Implementation shape**:
```php
// config/admin-navigation.php
return [
    ['key' => 'admin.dashboard',           'label_key' => 'admin.nav_dashboard',    'icon' => '…'],
    ['key' => 'admin.applications.index',  'label_key' => 'admin.nav_applications', 'icon' => '…'],
    ['key' => 'admin.visa-types.index',    'label_key' => 'admin.nav_visa_types',   'icon' => '…'],
    ['key' => 'admin.clients.index',       'label_key' => 'admin.nav_clients',      'icon' => '…'],
    ['key' => 'admin.task-builder.index',  'label_key' => 'admin.nav_task_builder', 'icon' => '…'],
    ['key' => 'admin.reviewers.index',     'label_key' => 'admin.nav_reviewers',    'icon' => '…'],
    ['key' => 'admin.users.index',         'label_key' => 'admin.nav_users',        'icon' => '…'],
];
```

**Alternatives considered**:
- View composer sharing nav data globally → rejected because global state increases coupling; config array in component is self-contained.
- Hardcoded links in layout → rejected because adding a section would require editing the layout (violates FR-018).
- Database-driven navigation → overkill; admin nav items do not change at runtime.

---

## 2. Dashboard Widget Independent Failure

**Decision**: Each widget's data is loaded by the controller wrapped in a `try/catch`. A structured result array `['data' => ..., 'error' => null]` is passed to the view. The Blade component renders the error state if `$error` is non-null.

**Rationale**: Keeps error handling in the controller (per constitution Principle II: controllers handle HTTP). No Blade `@try/@catch` (which would couple exception handling to view rendering). Each card is visually independent — one failure does not affect others (satisfies FR-008a).

**Pattern**:
```php
// AdminDashboardService::loadSafely(callable $fn): array
try { return ['data' => $fn(), 'error' => null]; }
catch (\Throwable $e) { Log::error(…); return ['data' => null, 'error' => 'Unable to load']; }
```

**Alternatives considered**:
- Single query for all counts → rejected because one failure brings down all counts.
- Blade `@try/@catch` → rejected; violates Principle II.
- Vue/React async widgets → out of scope; Blade SSR only in this phase.

---

## 3. Admin List Table Pattern

**Decision**: Reusable Blade component `<x-admin.table>` accepting columns, rows, pagination, search, and sort props. Controller handles search + sort via query string; `paginate(15)` for pagination. Search triggers on form submit (Enter key or button click) — no real-time keystroke filtering.

**Rationale**: Server-side only (no Livewire/AJAX). Query string approach preserves search/sort state across page refresh and browser back navigation. Empty-state message rendered via `@forelse`. Satisfies FR-012 through FR-016.

**Sort default**: `orderBy('created_at', 'desc')` applied in all base queries (satisfies FR-012a).

**Alternatives considered**:
- AJAX table updates without Livewire → rejected; requires custom JavaScript state management, harder to test.
- Livewire → explicitly excluded from tech stack.
- Inline pagination per-view → rejected; not reusable.

---

## 4. Audit Logging for Admin Actions

**Decision**: Explicit calls to `AuditLogService::log()` from within Service methods. Controllers call services, services call the audit logger.

**Rationale**: The `AuditLogService` is already implemented and actively used (in `UserService`, `PaymentService`). Constitution Principle II states Services own business logic — audit logging is part of the business operation. This is the established, tested pattern. Satisfies FR-019–FR-021.

**Standard audit events** (to be recorded by Phase 6 sub-feature services):
| Event key | Triggered when |
|-----------|---------------|
| `visa_type_created` | Admin creates a visa type |
| `visa_type_updated` | Admin edits a visa type |
| `visa_type_deactivated` | Admin deactivates a visa type |
| `reviewer_assigned` | Admin assigns a reviewer to an application |
| `reviewer_removed` | Admin removes a reviewer from an application |
| `client_deactivated` | Admin deactivates a client account |
| `application_status_changed` | Admin manually changes an application status |
| `task_template_created` | Admin creates a task template |

**Alternatives considered**:
- Laravel Observers → rejected; observers fire on model lifecycle events, not on explicit admin intent. Harder to distinguish "admin deactivated" vs. "system deactivated".
- Middleware-based logging → rejected; cannot capture action intent, fires for every request.

---

## 5. Breadcrumb Pattern

**Decision**: Per-controller `$breadcrumbs` array passed to the view, rendered by `<x-admin.breadcrumb>` component. An `AppServiceProvider` view composer provides a sensible default (Admin → Dashboard) for any admin view that doesn't set its own.

**Rationale**: Zero external packages. Breadcrumbs are set explicitly per-controller action, so each page controls its own trail. The view composer ensures no admin page renders without at least the root breadcrumb. Satisfies FR-003.

**Format**:
```php
$breadcrumbs = [
    ['label' => __('admin.nav_dashboard'), 'route' => 'admin.dashboard'],
    ['label' => __('admin.nav_applications'), 'route' => 'admin.applications.index'],
    ['label' => $application->reference_number, 'route' => null], // current page = no link
];
```

**Alternatives considered**:
- `diglactic/laravel-breadcrumbs` package → rejected; adds a dependency for a trivially simple data structure.
- Hardcoded breadcrumbs in layout → rejected; layout doesn't know which page is active.

---

## 6. Multi-Language Constitution Compliance

**Decision**: All admin Blade strings MUST use `__('admin.*')` lang keys. A `lang/en/admin.php` and `lang/ar/admin.php` are created. English translations are complete for Phase 6. Arabic translations for admin panel strings are stubbed with English fallbacks for Phase 6, to be completed in Phase 9.

**Rationale**: Constitution Principle X (Multi-Language) states: "Hardcoding English strings in Blade templates or PHP is forbidden." The spec's original assumption ("admin panel English-only for Phase 6") was a spec-level simplification that CANNOT override the constitution. The cost of using lang keys now is minimal; retrofitting later is expensive.

**Note on spec assumption**: The assumption "Admin panel is English-only for Phase 6" is revised to: "Admin panel uses full localization infrastructure (`__()` calls) for Phase 6. Arabic string translations are deferred to Phase 9." This satisfies the constitution without requiring Arabic UI content immediately.
