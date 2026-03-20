# Route Contracts: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation`
**Date**: 2026-03-20
**Prefix**: `/admin` | **Route name prefix**: `admin.`
**Middleware**: `auth`, `verified`, `can:dashboard.admin`

---

## Existing Routes (Retained, Refactored to Shared Pattern)

These routes already exist but their views will be refactored to use the shared admin layout and table pattern.

| Method | URI | Controller | Route Name | Notes |
|--------|-----|-----------|------------|-------|
| GET | `/admin/applications` | `Admin\ApplicationController@index` | `admin.applications.index` | Refactored to shared list pattern |
| GET | `/admin/users` | `Admin\UserController@index` | `admin.users.index` | Refactored to shared list pattern |

---

## New Routes (Phase 6 Foundation)

### Admin Dashboard

| Method | URI | Controller | Route Name |
|--------|-----|-----------|------------|
| GET | `/admin/dashboard` | `Admin\DashboardController@index` | `admin.dashboard` |

**Response**: View `admin.dashboard` with:
- `$widgets['pending_count']` → `['data' => int, 'error' => string|null]`
- `$widgets['active_count']` → `['data' => int, 'error' => string|null]`
- `$widgets['client_count']` → `['data' => int, 'error' => string|null]`
- `$widgets['recent']` → `['data' => Collection|null, 'error' => string|null]`

**Access**: Admin role required. Redirect to `/login` if unauthenticated. HTTP 403 if authenticated non-admin.

---

### Placeholder Section Routes

These routes return stub views until their Phase 6 sub-specs are implemented. Each returns HTTP 200 with a "Coming Soon" placeholder view.

| Method | URI | Controller | Route Name |
|--------|-----|-----------|------------|
| GET | `/admin/visa-types` | `Admin\VisaTypeController@index` | `admin.visa-types.index` |
| GET | `/admin/clients` | `Admin\ClientController@index` | `admin.clients.index` |
| GET | `/admin/task-builder` | `Admin\TaskBuilderController@index` | `admin.task-builder.index` |
| GET | `/admin/reviewers` | `Admin\ReviewerController@index` | `admin.reviewers.index` |

> **Note**: These controllers contain only an `index()` method returning a placeholder view for Phase 6 foundation. Full CRUD methods are added when each sub-spec is implemented.

---

## Access Control Contract

All routes in the `admin.*` namespace are wrapped in:
```
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(...)
```

Authorization is enforced via `can:dashboard.admin` middleware on the group, which maps to the `dashboard.admin` permission in `spatie/laravel-permission`. This permission is already seeded and granted only to the `admin` role.

**Denial behaviour**:
- Unauthenticated → redirect to `/login` (preserving intended URL via `intended()`)
- Authenticated, non-admin → HTTP 403 with view `errors.403`

---

## Request / Response Shapes

### `GET /admin/dashboard`

No request parameters.

Response view data:
```
widgets: {
  pending_count: { data: integer|null, error: string|null }
  active_count:  { data: integer|null, error: string|null }
  client_count:  { data: integer|null, error: string|null }
  recent:        { data: VisaApplication[]|null, error: string|null }
}
breadcrumbs: [
  { label: string, route: string|null }
]
```

### `GET /admin/applications` (refactored)

Query parameters (all optional):

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `search` | string | — | Filters by reference number or client name |
| `sort_by` | string | `created_at` | Column to sort by |
| `sort_dir` | `asc\|desc` | `desc` | Sort direction |
| `page` | integer | `1` | Pagination page number |

Response view data:
```
applications: LengthAwarePaginator (15 per page)
breadcrumbs: [{ label, route }]
```

---

## URL Redirect Mapping

| Trigger | Redirects to |
|---------|-------------|
| Admin visits `/dashboard` (generic) | `/admin/dashboard` |
| Any `/admin/*` as unauthenticated | `/login` (with `intended` URL stored) |
| Any `/admin/*` as non-admin | HTTP 403 error page |
