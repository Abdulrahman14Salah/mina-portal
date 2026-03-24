# Data Model: Menu Unification — Foundation and Architecture

**Branch**: `011-menu-unification-foundation` | **Date**: 2026-03-22

> This feature has no new database tables. Navigation data lives in a PHP config file (version-controlled). The "data model" describes the config schema and the service interface contract.

---

## Config Schema: Navigation Item

Each entry in `config/navigation.php` is an associative array with the following fields:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label_key` | string | Yes | Translation key resolved via `__(key)`. Must exist in `resources/lang/en/navigation.php` and `resources/lang/ar/navigation.php`. |
| `route` | string | Yes | Named Laravel route. Must exist in `routes/web.php`. |
| `route_params` | array | No | Parameters passed to `route()`. Used for client tab items (e.g., `['tab' => 'documents']`). Defaults to `[]`. |
| `roles` | string[] | Yes | Array of role slugs that can see this item. Valid values: `'admin'`, `'reviewer'`, `'client'`. Use multiple roles for shared items. |
| `active_pattern` | string\|null | No | Route name pattern for active-state matching via `request()->routeIs()`. Supports wildcards (e.g., `admin.applications.*`). If `null`, exact route name is used. |
| `position` | int | Yes | Display order within the visible items for a role. Lower number = appears first. Position values need not be contiguous. |

### Example Entry

```php
[
    'label_key'     => 'navigation.admin_applications',
    'route'         => 'admin.applications.index',
    'route_params'  => [],
    'roles'         => ['admin'],
    'active_pattern'=> 'admin.applications.*',
    'position'      => 20,
],
```

### Client Tab Entry Example

```php
[
    'label_key'     => 'navigation.client_tab_documents',
    'route'         => 'client.dashboard',
    'route_params'  => ['tab' => 'documents'],
    'roles'         => ['client'],
    'active_pattern'=> null,
    'position'      => 20,
],
```

---

## Full Navigation Item Catalogue

### Admin Items (role: `admin`)

| Position | Label Key | Route | Active Pattern |
|----------|-----------|-------|----------------|
| 10 | `navigation.admin_dashboard` | `admin.dashboard` | `null` |
| 20 | `navigation.admin_applications` | `admin.applications.index` | `admin.applications.*` |
| 30 | `navigation.admin_visa_types` | `admin.visa-types.index` | `admin.visa-types.*` |
| 40 | `navigation.admin_clients` | `admin.clients.index` | `admin.clients.*` |
| 50 | `navigation.admin_task_builder` | `admin.task-builder.index` | `admin.task-builder.*` |
| 60 | `navigation.admin_reviewers` | `admin.reviewers.index` | `admin.reviewers.*` |
| 70 | `navigation.admin_users` | `admin.users.index` | `admin.users.*` |

### Reviewer Items (role: `reviewer`)

| Position | Label Key | Route | Active Pattern |
|----------|-----------|-------|----------------|
| 10 | `navigation.reviewer_dashboard` | `reviewer.dashboard` | `null` |
| 20 | `navigation.reviewer_applications` | `reviewer.dashboard` | `reviewer.applications.*` |

### Client Items (role: `client`)

| Position | Label Key | Route | Route Params | Active Pattern |
|----------|-----------|-------|-------------|----------------|
| 10 | `navigation.client_overview` | `client.dashboard` | `[]` | `null` |
| 20 | `navigation.client_documents` | `client.dashboard` | `['tab'=>'documents']` | `null` |
| 30 | `navigation.client_tasks` | `client.dashboard` | `['tab'=>'tasks']` | `null` |
| 40 | `navigation.client_payments` | `client.dashboard` | `['tab'=>'payments']` | `null` |
| 50 | `navigation.client_timeline` | `client.dashboard` | `['tab'=>'timeline']` | `null` |
| 60 | `navigation.client_messages` | `client.dashboard` | `['tab'=>'messages']` | `null` |
| 70 | `navigation.client_profile` | `client.dashboard` | `['tab'=>'profile']` | `null` |
| 80 | `navigation.client_support` | `client.dashboard` | `['tab'=>'support']` | `null` |

---

## MenuService Interface

```
MenuService::getForUser(User $user): array
```

**Input**: Authenticated `User` model (with roles loaded).
**Output**: Array of navigation item arrays, filtered to the user's roles, sorted by `position` ascending.
**Behaviour**:
- Reads `config('navigation')` (full item list).
- For each item, checks if the user has at least one role from `$item['roles']` using `$user->hasRole()`.
- Sorts passing items by `position`.
- Returns filtered, sorted array. Empty array if no items match.
- A user with multiple roles sees the union of all permitted items, deduplicated by `route` + `route_params` combination.

---

## Translation Keys

### `resources/lang/en/navigation.php`

```php
return [
    // Admin
    'admin_dashboard'     => 'Dashboard',
    'admin_applications'  => 'Applications',
    'admin_visa_types'    => 'Visa Types',
    'admin_clients'       => 'Clients',
    'admin_task_builder'  => 'Task Builder',
    'admin_reviewers'     => 'Reviewers',
    'admin_users'         => 'Users',
    // Reviewer
    'reviewer_dashboard'     => 'Dashboard',
    'reviewer_applications'  => 'Applications',
    // Client tabs
    'client_overview'   => 'Overview',
    'client_documents'  => 'Documents',
    'client_tasks'      => 'Tasks',
    'client_payments'   => 'Payments',
    'client_timeline'   => 'Timeline',
    'client_messages'   => 'Messages',
    'client_profile'    => 'Profile',
    'client_support'    => 'Support',
];
```

### `resources/lang/ar/navigation.php`

All keys above translated to Arabic (RTL). See contracts/nav-component.md for rendering requirements.

---

## No Database Changes

This feature introduces zero new migrations. The `navigation.php` config file is the sole persistent artifact beyond the Blade component and service files.
