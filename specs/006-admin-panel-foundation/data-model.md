# Data Model: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation`
**Date**: 2026-03-20

---

## Overview

The admin panel foundation introduces **no new database tables**. It works exclusively with existing models and adds new service/view layers on top. All counts and lists on the dashboard home are queries against existing tables.

---

## Existing Models Consumed

### VisaApplication

Used for dashboard summary counts and the recent-applications list.

| Query | Purpose |
|-------|---------|
| `VisaApplication::count()` where `status != 'rejected'` | Total active applications count |
| `VisaApplication::where('status', 'pending_review')->count()` | Pending review count (dashboard card) |
| `VisaApplication::latest()->take(5)->with(['user', 'visaType'])->get()` | Recent 5 applications list |

Relevant existing columns: `id`, `user_id`, `visa_type_id`, `reference_number`, `status`, `full_name`, `created_at`.

### User

Used for the total clients count card.

| Query | Purpose |
|-------|---------|
| `User::role('client')->count()` | Total registered client accounts |

### AuditLog

Written to by admin actions. No new columns — uses existing `event`, `user_id`, `metadata`, `created_at` columns.

New event keys written by Phase 6 admin services:

| Event key | Metadata shape |
|-----------|---------------|
| `visa_type_created` | `{ visa_type_id, name }` |
| `visa_type_updated` | `{ visa_type_id, changed_fields[] }` |
| `visa_type_deactivated` | `{ visa_type_id, name }` |
| `reviewer_assigned` | `{ application_id, reviewer_id, reference_number }` |
| `reviewer_removed` | `{ application_id, reviewer_id, reference_number }` |
| `client_deactivated` | `{ client_id, client_email }` |
| `application_status_changed` | `{ application_id, reference_number, old_status, new_status }` |
| `task_template_created` | `{ task_id, name, visa_type_id }` |

---

## New Non-Database Structures

### Admin Navigation Config

**Location**: `config/admin-navigation.php`
**Type**: PHP array (not persisted to database)

Each navigation item:

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | Named route key (e.g., `admin.applications.index`) |
| `label_key` | string | Lang file key (e.g., `admin.nav_applications`) |
| `icon` | string | Icon identifier for the UI |
| `active_pattern` | string\|null | Optional route pattern for active detection (e.g., `admin.applications.*`) |

### Dashboard Widget Result

**Type**: PHP array (in-memory, per request)

Each summary card receives a structured result:

| Field | Type | Description |
|-------|------|-------------|
| `data` | mixed\|null | The loaded value (integer count or collection) |
| `error` | string\|null | Non-null if the data could not be loaded; contains display message |

### Breadcrumb Item

**Type**: PHP array (in-memory, per request, passed to view)

| Field | Type | Description |
|-------|------|-------------|
| `label` | string | Translated display label |
| `route` | string\|null | Named route for the link; `null` for the current (last) item |

---

## New Lang Keys

### `lang/en/admin.php` (new file)

```php
[
    // Navigation
    'nav_dashboard'     => 'Dashboard',
    'nav_applications'  => 'Applications',
    'nav_visa_types'    => 'Visa Types',
    'nav_clients'       => 'Clients',
    'nav_task_builder'  => 'Task Builder',
    'nav_reviewers'     => 'Reviewers',
    'nav_users'         => 'Users',

    // Dashboard
    'dashboard_title'          => 'Admin Dashboard',
    'active_applications'      => 'Active Applications',
    'pending_review'           => 'Pending Review',
    'total_clients'            => 'Total Clients',
    'recent_applications'      => 'Recent Applications',
    'view_all'                 => 'View All',
    'widget_error'             => 'Unable to load',
    'no_recent_applications'   => 'No applications yet.',

    // List UI
    'search_placeholder'  => 'Search…',
    'search_button'       => 'Search',
    'no_records'          => 'No records found.',
    'confirm_action'      => 'Are you sure?',
    'action_view'         => 'View',
    'action_edit'         => 'Edit',
    'action_deactivate'   => 'Deactivate',
    'action_delete'       => 'Delete',

    // Table columns (shared)
    'col_reference'       => 'Reference',
    'col_client'          => 'Client',
    'col_visa_type'       => 'Visa Type',
    'col_status'          => 'Status',
    'col_submitted'       => 'Submitted',
    'col_actions'         => 'Actions',

    // Access denied
    'access_denied'       => 'You do not have permission to access this area.',
]
```

### `lang/ar/admin.php` (new file — Phase 9 Arabic content deferred)

Arabic keys are stubbed with English fallbacks for Phase 6. Phase 9 will replace with full Arabic translations.

---

## No Schema Changes

No migrations are required for this feature. The foundation layer is entirely view/service/config work on top of existing schema.
