# Interface Contract: MenuService

**Feature**: 011-menu-unification-foundation
**Type**: Internal PHP service contract (no HTTP API)

---

## Service: `App\Services\Navigation\MenuService`

### Method: `getForUser(User $user): array`

Returns the filtered, position-sorted list of navigation items visible to the given user.

**Parameters**:
- `$user` — `App\Models\User` — must have roles loaded (Spatie). If roles are not loaded, the method eager-loads them internally.

**Returns**: `array` — ordered array of navigation item arrays. Each item has the shape:

```php
[
    'label_key'     => string,   // e.g., 'navigation.admin_dashboard'
    'route'         => string,   // e.g., 'admin.dashboard'
    'route_params'  => array,    // e.g., [] or ['tab' => 'documents']
    'active_pattern'=> ?string,  // e.g., 'admin.applications.*' or null
    'position'      => int,      // e.g., 10
]
```

**Guarantees**:
- Returns empty array (`[]`) if user has no matching roles or no items are defined.
- Items are sorted ascending by `position`.
- Multi-role users see the deduplicated union of permitted items.
- Never throws; invalid config entries (missing required keys) are skipped with a logged warning.

**Does NOT**:
- Perform HTTP redirect.
- Write to the database.
- Check route existence at runtime.

---

## View Composer: `App\View\Composers\MenuComposer`

### Method: `compose(View $view): void`

Injected automatically before nav component views render. Calls `MenuService::getForUser()` with the currently authenticated user and binds `$menuItems` to the view.

**Views composed** (registered in `AppServiceProvider::boot()`):
- `components.admin.nav`
- `components.reviewer.nav`
- `components.client.nav`

**Injects**:
- `$menuItems` — array (same shape as `MenuService::getForUser()` output)

**Behaviour when unauthenticated**: If `Auth::user()` returns `null` (should not occur under `auth` middleware), injects empty array and logs a warning. Does not throw.

---

## Blade Component Contract: `<x-nav.main />`

A single shared Blade component (`resources/views/components/nav/main.blade.php`) renders a navigation bar from the injected `$menuItems`.

**Props**: none — receives `$menuItems` from the View Composer.

**Renders**:
- One `<a>` per item using `route($item['route'], $item['route_params'])`.
- Label via `__($item['label_key'])`.
- Active state: applies active CSS class when `request()->routeIs($item['active_pattern'] ?? $item['route'])`.

**RTL**: Applies `dir="rtl"` or inherits from the page `<html>` direction. Does not manage locale itself.

**Accessibility**: Each `<a>` element must be reachable via keyboard; `<nav>` element must have an `aria-label`.

---

## Config Contract: `config/navigation.php`

**Required keys per item**: `label_key` (string), `route` (string), `roles` (non-empty array), `position` (int).
**Optional keys per item**: `route_params` (array, defaults `[]`), `active_pattern` (string|null, defaults `null`).

**Validation rule** (enforced by MenuService): If a required key is missing, the item is silently skipped and a `warning`-level log entry is emitted with the malformed item index.

---

## Tests Required

| Test | Assertion |
|------|-----------|
| Admin user receives only admin items | `MenuService::getForUser($admin)` returns 7 admin items, no reviewer/client items |
| Reviewer user receives only reviewer items | Returns 2 reviewer items, no admin/client items |
| Client user receives only client items | Returns 8 client items, no admin/reviewer items |
| Multi-role user sees union without duplicates | User with admin+reviewer roles sees 9 items (7 + 2), no duplicates |
| Items sorted by position | First item has lowest `position` value |
| Unauthenticated (null user) returns empty array | No exception thrown |
| Missing required config key is skipped | Item with no `roles` key is excluded; warning logged |
