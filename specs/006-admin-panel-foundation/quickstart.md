# Quickstart: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation`
**Date**: 2026-03-20

---

## Prerequisites

- PHP 8.2+, Composer, MAMP MySQL running on port 8889
- Laravel app installed and migrated (`php artisan migrate`)
- `RolePermissionSeeder` run (admin role + `dashboard.admin` permission seeded)
- An admin user exists (seed via `DatabaseSeeder` or tinker)

---

## What Gets Built

This feature adds the admin panel structural layer:

```
New files:
  config/admin-navigation.php
  app/Http/Controllers/Admin/DashboardController.php
  app/Http/Controllers/Admin/VisaTypeController.php      (stub)
  app/Http/Controllers/Admin/ClientController.php        (stub)
  app/Http/Controllers/Admin/TaskBuilderController.php   (stub)
  app/Http/Controllers/Admin/ReviewerController.php      (stub)
  app/Services/Admin/AdminDashboardService.php
  app/View/Composers/AdminBreadcrumbComposer.php
  resources/views/admin/dashboard.blade.php
  resources/views/admin/placeholder.blade.php
  resources/views/components/admin/nav.blade.php
  resources/views/components/admin/table.blade.php
  resources/views/components/admin/dashboard-card.blade.php
  resources/views/components/admin/breadcrumb.blade.php
  lang/en/admin.php
  lang/ar/admin.php
  tests/Feature/Admin/AdminDashboardTest.php

Modified files:
  routes/web.php                         (add admin dashboard + placeholder routes)
  app/Providers/AppServiceProvider.php   (register AdminBreadcrumbComposer)
  resources/views/admin/applications/index.blade.php  (refactor to shared table pattern)
  resources/views/admin/users/index.blade.php         (refactor to shared table pattern)
```

---

## Verify It Works

### 1. Access admin dashboard

Log in as an admin user, then visit:
```
http://localhost:8888/admin/dashboard
```

You should see:
- Sidebar/top navigation with 7 section links
- Active state on "Dashboard"
- Three summary count cards (Active Applications, Pending Review, Total Clients)
- Recent applications list (up to 5 rows)
- Breadcrumb: "Admin"

### 2. Navigate to all sections

Click each nav link — all should return HTTP 200 (placeholder views for stub sections):
- Applications → existing list (refactored)
- Visa Types → placeholder
- Clients → placeholder
- Task Builder → placeholder
- Reviewers → placeholder
- Users → existing list (refactored)

### 3. Verify access control

```bash
# As a client user — should get 403
php artisan tinker --execute="
\$client = App\Models\User::role('client')->first();
// Then visit /admin/dashboard in browser as this user
"
```

Or run the feature tests:
```bash
php artisan test --filter AdminDashboardTest
```

### 4. Verify search on Applications list

Visit `/admin/applications?search=REF-` and confirm the table filters. Press Enter or click Search — the page reloads with filtered results.

### 5. Verify sort on Applications list

Click any column header — the URL should update with `?sort_by=X&sort_dir=asc` and results should reorder.

### 6. Verify audit log (if destructive actions available)

After any admin action (once sub-features are built), check:
```bash
php artisan tinker --execute="
DB::table('audit_logs')->latest()->take(5)->get()->pluck('event');
"
```

---

## Run Tests

```bash
php artisan test --filter Admin
```

All admin tests should pass. The suite covers:
- Dashboard loads for admin (200)
- Dashboard blocked for client (403)
- Dashboard blocked for reviewer (403)
- Unauthenticated redirected to login
- Recent applications list displayed
- Widget error state when data unavailable
- Applications list searchable
- Applications list sortable
- Applications list paginated at 15

---

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `/admin/dashboard` returns 403 for admin | `dashboard.admin` permission not seeded | Run `php artisan db:seed --class=RolePermissionSeeder` |
| Navigation links show `Route not found` | Placeholder routes not registered | Check `routes/web.php` for all `admin.*` routes |
| `$breadcrumbs` undefined in view | View composer not registered | Check `AppServiceProvider::boot()` for `AdminBreadcrumbComposer` registration |
| Dashboard cards all show "Unable to load" | Database connection issue | Check MySQL is running and `.env` DB credentials |
| Arabic strings show English text | Arabic lang file not populated | Expected for Phase 6 — Arabic content added in Phase 9 |
