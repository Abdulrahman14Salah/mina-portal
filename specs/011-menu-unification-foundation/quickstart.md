# Quickstart: Menu Unification — Foundation and Architecture

**Branch**: `011-menu-unification-foundation` | **Date**: 2026-03-22

---

## What This Feature Delivers

A centralized `config/navigation.php` replaces `config/admin-navigation.php`. A new `MenuService` filters items by role. A `MenuComposer` injects `$menuItems` into all nav components. The three role layouts stay separate but each includes the same `<x-nav.main />` component. The reviewer gets a nav bar for the first time. All labels are translated (EN + AR).

---

## Acceptance Test Scenarios

### Scenario 1 — Admin sees admin-only items

1. Log in as a user with the `admin` role.
2. Navigate to any admin page.
3. **Expect**: Navigation bar shows: Dashboard, Applications, Visa Types, Clients, Task Builder, Reviewers, Users.
4. **Expect**: No reviewer or client items are visible.
5. Navigate to `/admin/applications/1` (application detail).
6. **Expect**: "Applications" menu item is visually highlighted.

### Scenario 2 — Reviewer sees reviewer-only items

1. Log in as a user with the `reviewer` role.
2. Navigate to `/reviewer/dashboard`.
3. **Expect**: Navigation bar shows: Dashboard, Applications.
4. **Expect**: No admin or client items are visible.
5. Navigate to `/reviewer/applications/1`.
6. **Expect**: "Applications" menu item is visually highlighted.

### Scenario 3 — Client sees client tabs only

1. Log in as a user with the `client` role.
2. Navigate to `/client/dashboard`.
3. **Expect**: Tab navigation shows: Overview, Documents, Tasks, Payments, Timeline, Messages, Profile, Support.
4. **Expect**: No admin or reviewer items are visible.
5. Click "Documents" tab.
6. **Expect**: "Documents" tab is highlighted.

### Scenario 4 — Arabic locale

1. Switch locale to Arabic (`POST /language/ar`).
2. Navigate to any role dashboard.
3. **Expect**: Menu item labels appear in Arabic.
4. **Expect**: Navigation renders correctly (RTL where page applies RTL).

### Scenario 5 — Dashboard link resolves correctly per role

1. As Admin: click Dashboard link → lands on `/admin/dashboard`.
2. As Reviewer: click Dashboard link → lands on `/reviewer/dashboard`.
3. As Client: click Overview tab → lands on `/client/dashboard`.

### Scenario 6 — Developer adds a new menu item in one place

1. Add a new entry to `config/navigation.php` with `roles: ['admin']`.
2. **Expect**: The item appears in the admin nav without any other file changes.
3. **Expect**: No reviewer or client can see it.

### Scenario 7 — Direct URL access still blocked by backend

1. Log in as a Client.
2. Attempt to navigate directly to `/admin/dashboard`.
3. **Expect**: 403 Forbidden (policy/middleware blocks access).
4. **Expect**: The admin nav items are not visible in the client's navigation.

---

## Files Changed / Created

| File | Status | Purpose |
|------|--------|---------|
| `config/navigation.php` | New | Unified config replacing `config/admin-navigation.php` |
| `config/admin-navigation.php` | Removed | Replaced by unified config |
| `app/Services/Navigation/MenuService.php` | New | Filters items by user role |
| `app/View/Composers/MenuComposer.php` | New | Injects `$menuItems` into nav components |
| `app/Providers/AppServiceProvider.php` | Updated | Registers MenuComposer |
| `resources/views/components/nav/main.blade.php` | New | Shared nav rendering component |
| `resources/views/components/admin/nav.blade.php` | Updated | Uses `$menuItems` from composer |
| `resources/views/components/reviewer/nav.blade.php` | New | Reviewer nav using `$menuItems` |
| `resources/views/components/client/nav.blade.php` | Updated | Uses `$menuItems` instead of inline array |
| `resources/views/components/reviewer-layout.blade.php` | Updated | Includes `<x-reviewer.nav />` |
| `resources/lang/en/navigation.php` | New | English nav labels |
| `resources/lang/ar/navigation.php` | New | Arabic nav labels |
| `tests/Feature/Navigation/MenuServiceTest.php` | New | Unit/feature tests for MenuService |
| `tests/Feature/Navigation/MenuVisibilityTest.php` | New | Role-based visibility integration tests |

---

## Running Tests

```bash
php artisan test --filter Navigation
```

All existing tests must continue to pass:

```bash
php artisan test
```
